<?php
// app/Http/Controllers/RecipeController.php
// ------------------------------------------------------------------

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Recipe;
use App\Models\LaborCost;
use App\Models\Department;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use App\Models\RecipeCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\LaborCostCalculator; // (se deja para otros lugares que todavía lo usan)
use Illuminate\Http\RedirectResponse;

class RecipeController extends Controller
{
    /**
     * Devuelve las últimas tarifas globales y por departamento para un departamento (AJAX).
     * Usa SOLO los overrides guardados; si no hay, vuelve a la tarifa global del usuario.
     */
    public function departmentRates(Department $department)
    {
        $user = Auth::user();
        $rootId = $this->groupRootId($user);

        $base = $this->latestGlobalLaborCost($rootId);
        $baseShop = (float) ($base->shop_cost_per_min ?? 0);
        $baseExt  = (float) ($base->external_cost_per_min ?? 0);

        $override = $this->latestDeptOverride($rootId, $department->id);

        // usar override solo si al menos una tarifa es > 0
        $shop = $override && ((float)$override->shop_cost_per_min > 0 || (float)$override->external_cost_per_min > 0)
            ? (float)$override->shop_cost_per_min
            : $baseShop;

        $external = $override && ((float)$override->shop_cost_per_min > 0 || (float)$override->external_cost_per_min > 0)
            ? (float)$override->external_cost_per_min
            : $baseExt;

        return response()->json([
            'shop'     => round($shop, 6),
            'external' => round($external, 6),
            'source'   => $override ? 'override' : 'global',
        ]);
    }

    /**
     * Listar recetas.
     */
    public function index()
    {
        $user = Auth::user();

        // Visibilidad de grupo: propietario + todos los usuarios creados por el propietario (o usuario + propietario si es miembro)
        $visibleUserIds = is_null($user->created_by)
            ? User::where('created_by', $user->id)->pluck('id')->push($user->id)->unique()
            : collect([$user->id, $user->created_by])->unique();

        // cargar con eager load todo lo necesario para evitar N+1
        $recipes = Recipe::with([
            'category:id,name',
            'department:id,name,share_percent',
            'ingredients.ingredient',   // => ingredient price_per_kg se usa para el cálculo en vivo
            'user'
        ])
            ->whereIn('user_id', $visibleUserIds)
            ->get();

        // 🔄 Recalcular los costes en vivo para que el listado refleje los últimos precios/tarifas
        $recipes->each(function ($r) use ($user) {
            // --- coste de ingredientes del lote según precios actuales
            $batchIngCost = $r->ingredients->sum(function ($ri) {
                $priceKg = (float) (optional($ri->ingredient)->price_per_kg ?? 0);
                return ($ri->quantity_g / 1000.0) * $priceKg;
            });
            $batchIngCost = round($batchIngCost, 2);

            // ayudantes piezas / kg
            $pcs = max(1, (int) ($r->total_pieces ?? 0));
            $weightG = (float) ($r->recipe_weight ?? 0);
            if ($weightG <= 0) {
                // fallback: suma de cantidades de las líneas si recipe_weight no está establecido
                $weightG = (float) $r->ingredients->sum('quantity_g');
            }
            $kg = max(0.001, $weightG / 1000.0); // proteger contra división entre cero

            // --- coste unitario de ingredientes
            $unitIngCost = $r->sell_mode === 'piece'
                ? round($batchIngCost / $pcs, 2)
                : round($batchIngCost / $kg, 2);

            // --- mano de obra: tarifa efectiva dependiente del departamento (override solo si está guardado)
            $owner = $r->user ?: $user;
            $rates = $this->effectiveRatesFor($owner, $r->department); // ['shop'=>x,'external'=>y]
            $rate  = $r->labor_cost_mode === 'external' ? ($rates['external'] ?? 0) : ($rates['shop'] ?? 0);

            $batchLaborCost = round(($r->labour_time_min ?? 0) * (float) $rate, 2);
            $unitLaborCost  = $r->sell_mode === 'piece'
                ? round($batchLaborCost / $pcs, 2)
                : round($batchLaborCost / $kg, 2);

            // --- empaquetado: por pieza o por kg (misma lógica que en tu JS del formulario)
            $pack = (float) ($r->packing_cost ?? 0);
            $unitPack = $r->sell_mode === 'piece'
                ? round($pack / $pcs, 2)
                : round($pack, 2);

            // --- coste unitario final DESPUÉS del empaquetado
            $unitTotal = round($unitIngCost + $unitLaborCost + $unitPack, 2);

            // exponer a la vista
            $r->setAttribute('unit_ing_cost', $unitIngCost);
            $r->setAttribute('batch_labor_cost', $batchLaborCost);
            $r->setAttribute('unit_labor_cost', $unitLaborCost);
            $r->setAttribute('total_expense', $unitTotal);
        });

        $departments = Department::whereIn('user_id', $visibleUserIds)
            ->orderBy('name')
            ->get();

        $categories = RecipeCategory::with('user')
    ->where(function ($q) use ($user) {
        $q->where('user_id', $user->id)
          ->orWhere('status', 'Default');
    })
    ->orderByRaw("CASE WHEN status = 'Default' THEN 0 ELSE 1 END")
    ->orderBy('name')
    ->get();


        return view('frontend.recipe.index', compact('recipes', 'departments', 'categories'));
    }

    /**
     * Mostrar una receta.
     */
    public function show(Recipe $recipe)
    {
        $recipe->load([
            'category',
            'department',
            'ingredients.ingredient',
            'laborCostRate'
        ]);

        return view('frontend.recipe.show', compact('recipe'));
    }

    /**
     * Formulario de creación.
     */
    public function create()
    {
        $user = Auth::user();

        // Determinar el administrador raíz del grupo
        $groupRootId = $this->groupRootId($user);

        // Visibilidad de ingredientes = root + hijos
        $groupUserIds = User::where('created_by', $groupRootId)
            ->pluck('id')
            ->push($groupRootId)
            ->unique();

         $laborCost = $this->latestGlobalLaborCost($groupRootId);

         $ingredients = Ingredient::whereIn('user_id', $groupUserIds)
            ->orderBy('ingredient_name')
            ->get();

         $visibleUserIds = is_null($user->created_by)
            ? User::where('created_by', $user->id)->pluck('id')->push($user->id)->unique()
            : collect([$user->id, $user->created_by])->unique();

        $categories = RecipeCategory::with('user')
    ->where(function ($q) use ($user) {
        $q->where('user_id', $user->id)
          ->orWhere('status', 'Default');
    })
    ->orderByRaw("CASE WHEN status = 'Default' THEN 0 ELSE 1 END")
    ->orderBy('name')
    ->get();


         $departments = Department::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                    ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

         $ratesByDept = $this->buildDeptRatesMap($groupRootId, $departments, $laborCost);

         $alreadyAsIngredient = old('recipe_name')
            ? Ingredient::where('ingredient_name', old('recipe_name'))
            ->where('user_id', $user->id)
            ->exists()
            : false;

        return view('frontend.recipe.create', compact(
            'laborCost',
            'ingredients',
            'categories',
            'departments',
            'alreadyAsIngredient',
            'ratesByDept'
        ));
    }

    /**
     * Formulario de edición.
     */
    public function edit(Recipe $recipe)
    {
        $user = Auth::user();
        $groupRootId = $this->groupRootId($user);

        $recipe->load('ingredients');

        $laborCost = $this->latestGlobalLaborCost($groupRootId);

        $groupUserIds = User::where('created_by', $groupRootId)
            ->pluck('id')
            ->push($groupRootId)
            ->unique();

        $ingredients = Ingredient::whereIn('user_id', $groupUserIds)
            ->orderBy('ingredient_name')
            ->get();

        $visibleUserIds = is_null($user->created_by)
            ? User::where('created_by', $user->id)->pluck('id')->push($user->id)->unique()
            : collect([$user->id, $user->created_by])->unique();

        $categories = RecipeCategory::with('user')
    ->where(function ($q) use ($user) {
        $q->where('user_id', $user->id)
          ->orWhere('status', 'Default');
    })
    ->orderByRaw("CASE WHEN status = 'Default' THEN 0 ELSE 1 END")
    ->orderBy('name')
    ->get();


        $departments = Department::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                    ->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get();

        // Tarifas por departamento para el formulario (override solo si está guardado)
        $ratesByDept = $this->buildDeptRatesMap($groupRootId, $departments, $laborCost);

        $alreadyAsIngredient = Ingredient::where('ingredient_name', $recipe->recipe_name)
            ->where('user_id', $user->id)
            ->exists();

        return view('frontend.recipe.create', compact(
            'recipe',
            'laborCost',
            'ingredients',
            'categories',
            'departments',
            'alreadyAsIngredient',
            'ratesByDept'
        ));
    }

    /**
     * AJAX: calcular el coste de línea de ingrediente para una cantidad.
     */
    public function calculateCost(Request $request)
    {
        $ingredient = Ingredient::find($request->ingredient_id);

        if ($ingredient) {
            $pricePerGram = ($ingredient->price_per_kg ?? 0) / 1000;
            $totalCost    = $pricePerGram * (float) ($request->quantity ?? 0);
            return response()->json(['cost' => number_format($totalCost, 2)]);
        }

        return response()->json(['cost' => '0.00']);
    }

    /**
     * Guardar una receta nueva.
     */
    public function store(Request $request)
    {
        $user   = Auth::user();
        $rootId = $this->groupRootId($user);

        // 1) Always have a global/base labor cost row
        $base = LaborCost::firstOrCreate(
            ['user_id' => $rootId, 'department_id' => null],
            ['shop_cost_per_min' => 0, 'external_cost_per_min' => 0]
        );

        // 2) If a department override exists, use it; else use global
        $deptId = $request->input('department_id');
        $effective = $deptId
            ? LaborCost::where('user_id', $rootId)->where('department_id', $deptId)->latest('id')->first()
            : null;

        $effective = $effective ?: $base;

        // 3) Force labor_cost_id if missing/empty
        if (!$request->filled('labor_cost_id')) {
            $request->merge(['labor_cost_id' => $effective->id]);
        }


        $data = $request->validate([
            'recipe_name'             => 'required|string|max:255',
            'recipe_category_id'      => 'required|exists:recipe_categories,id',
            'department_id'           => 'required|exists:departments,id',
            'sell_mode'               => 'required|in:piece,kg',
            'selling_price_per_piece' => 'nullable|numeric|min:0',
            'selling_price_per_kg'    => 'nullable|numeric|min:0',
            'labor_cost_id'           => 'required|exists:labor_costs,id',
            'labor_time_input'        => 'required|integer|min:0',
            'labor_cost_mode'         => 'required|in:shop,external',
            'packing_cost'            => 'nullable|numeric|min:0',
            'production_cost_per_kg'  => 'required|numeric|min:0',
            'total_expense'           => 'required|numeric|min:0',
            'potential_margin'        => 'required|numeric',
            'potential_margin_pct'    => 'required|numeric',
            'ingredients'             => 'required|array|min:1',
            'ingredients.*.id'        => 'required|exists:ingredients,id',
            'ingredients.*.quantity'  => 'required|numeric|min:0',
            'total_pieces'            => 'nullable|numeric|min:0',
            'recipe_weight'           => 'nullable|numeric|min:0',
            'add_as_ingredient'       => 'sometimes|boolean',
            'vat_rate'                => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($data, $request) {
            $ingredientIds = collect($data['ingredients'])->pluck('id');
            $priceMap = Ingredient::whereIn('id', $ingredientIds)
                ->pluck('price_per_kg', 'id');

            $batchIngCost = 0.0;
            $sumWeightG   = 0.0;

            foreach ($data['ingredients'] as $line) {
                $qtyG     = (float) $line['quantity'];
                $priceKg  = (float) ($priceMap[$line['id']] ?? 0);
                $sumWeightG   += $qtyG;
                $batchIngCost += ($qtyG / 1000.0) * $priceKg;
            }
            $batchIngCost = round($batchIngCost, 2);

            if ($data['sell_mode'] === 'piece') {
                $pcs = (int) ($data['total_pieces'] ?? 0);
                $pcs = $pcs > 0 ? $pcs : 1;
                $unitIngCost = round($batchIngCost / $pcs, 2);
            } else {
                $wLossG = (float) ($data['recipe_weight'] ?? 0);
                if ($wLossG <= 0) {
                    $wLossG = $sumWeightG;
                }
                $kg = $wLossG > 0 ? ($wLossG / 1000.0) : 1;
                $unitIngCost = round($batchIngCost / $kg, 2);
            }

            $recipe = Recipe::create([
                'user_id'                 => Auth::id(),
                'recipe_name'             => $data['recipe_name'],
                'recipe_category_id'      => $data['recipe_category_id'],
                'department_id'           => $data['department_id'],
                'sell_mode'               => $data['sell_mode'],
                'selling_price_per_piece' => $data['selling_price_per_piece'] ?? 0,
                'selling_price_per_kg'    => $data['selling_price_per_kg']  ?? 0,
                'labor_cost_id'           => $data['labor_cost_id'],
                'labour_time_min'         => $data['labor_time_input'],
                'labor_cost_mode'         => $data['labor_cost_mode'],
                'packing_cost'            => $data['packing_cost'] ?? 0,
                'production_cost_per_kg'  => $data['production_cost_per_kg'],
                'total_expense'           => $data['total_expense'],
                'potential_margin'        => $data['potential_margin'],
                'potential_margin_pct'    => $data['potential_margin_pct'],
                'total_pieces'            => $data['total_pieces'] ?? 0,
                'recipe_weight'           => $data['recipe_weight'] ?? 0,
                'vat_rate'                => $data['vat_rate'] ?? 0,
                'add_as_ingredient'       => $request->boolean('add_as_ingredient'),
                'unit_ing_cost'           => $unitIngCost,
            ]);

            foreach ($data['ingredients'] as $line) {
                $recipe->ingredients()->create([
                    'ingredient_id' => $line['id'],
                    'quantity_g'    => $line['quantity'],
                ]);
            }

            if ($request->boolean('add_as_ingredient')) {
                Ingredient::create([
                    'ingredient_name' => $recipe->recipe_name,
                    'price_per_kg'    => $recipe->production_cost_per_kg, // €/kg (antes del empaquetado)
                    'user_id'         => Auth::id(),
                    'recipe_id'       => $recipe->id,
                ]);
            }
        });

        return redirect()->route('recipes.create')
            ->with('success', 'Receta guardada con éxito.');
    }

    /**
     * Actualizar una receta.
     */
    public function update(Request $request, Recipe $recipe)
    {
        $user   = Auth::user();
        $rootId = $this->groupRootId($user);

        // 1) Always have a global/base labor cost row
        $base = LaborCost::firstOrCreate(
            ['user_id' => $rootId, 'department_id' => null],
            ['shop_cost_per_min' => 0, 'external_cost_per_min' => 0]
        );

        // 2) If a department override exists, use it; else use global
        $deptId = $request->input('department_id');
        $effective = $deptId
            ? LaborCost::where('user_id', $rootId)->where('department_id', $deptId)->latest('id')->first()
            : null;

        $effective = $effective ?: $base;

        // 3) Force labor_cost_id if missing/empty
        if (!$request->filled('labor_cost_id')) {
            $request->merge(['labor_cost_id' => $effective->id]);
        }

        $data = $request->validate([
            'recipe_name'             => 'required|string|max:255',
            'recipe_category_id'      => 'required|exists:recipe_categories,id',
            'department_id'           => 'required|exists:departments,id',
            'sell_mode'               => 'required|in:piece,kg',
            'selling_price_per_piece' => 'nullable|numeric|min:0',
            'selling_price_per_kg'    => 'nullable|numeric|min:0',
            'labor_cost_id'           => 'required|exists:labor_costs,id',
            'labor_time_input'        => 'required|integer|min:0',
            'labor_cost_mode'         => 'required|in:shop,external',
            'packing_cost'            => 'nullable|numeric|min:0',
            'production_cost_per_kg'  => 'required|numeric|min:0',
            'total_expense'           => 'required|numeric|min:0',
            'potential_margin'        => 'required|numeric',
            'potential_margin_pct'    => 'required|numeric',
            'ingredients'             => 'required|array|min:1',
            'ingredients.*.id'        => 'required|exists:ingredients,id',
            'ingredients.*.quantity'  => 'required|numeric|min:0',
            'total_pieces'            => 'nullable|numeric|min:0',
            'recipe_weight'           => 'nullable|numeric|min:0',
            'add_as_ingredient'       => 'sometimes|boolean',
            'vat_rate'                => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($data, $request, $recipe) {

            $ingredientIds = collect($data['ingredients'])->pluck('id');
            $priceMap = Ingredient::whereIn('id', $ingredientIds)
                ->pluck('price_per_kg', 'id');

            $batchIngCost = 0.0;
            $sumWeightG   = 0.0;

            foreach ($data['ingredients'] as $line) {
                $qtyG     = (float) $line['quantity'];
                $priceKg  = (float) ($priceMap[$line['id']] ?? 0);
                $sumWeightG   += $qtyG;
                $batchIngCost += ($qtyG / 1000.0) * $priceKg;
            }
            $batchIngCost = round($batchIngCost, 2);

            if ($data['sell_mode'] === 'piece') {
                $pcs = (int) ($data['total_pieces'] ?? 0);
                $pcs = $pcs > 0 ? $pcs : 1;
                $unitIngCost = round($batchIngCost / $pcs, 2);
            } else {
                $wLossG = (float) ($data['recipe_weight'] ?? 0);
                if ($wLossG <= 0) {
                    $wLossG = $sumWeightG;
                }
                $kg = $wLossG > 0 ? ($wLossG / 1000.0) : 1;
                $unitIngCost = round($batchIngCost / $kg, 2);
            }

            $recipe->update([
                'recipe_name'             => $data['recipe_name'],
                'recipe_category_id'      => $data['recipe_category_id'],
                'department_id'           => $data['department_id'],
                'sell_mode'               => $data['sell_mode'],
                'selling_price_per_piece' => $data['selling_price_per_piece'] ?? 0,
                'selling_price_per_kg'    => $data['selling_price_per_kg']  ?? 0,
                'labor_cost_id'           => $data['labor_cost_id'],
                'labour_time_min'         => $data['labor_time_input'],
                'labor_cost_mode'         => $data['labor_cost_mode'],
                'packing_cost'            => $data['packing_cost'] ?? 0,
                'production_cost_per_kg'  => $data['production_cost_per_kg'],
                'total_expense'           => $data['total_expense'],
                'potential_margin'        => $data['potential_margin'],
                'potential_margin_pct'    => $data['potential_margin_pct'],
                'total_pieces'            => $data['total_pieces'] ?? 0,
                'recipe_weight'           => $data['recipe_weight'] ?? 0,
                'vat_rate'                => $data['vat_rate'] ?? 0,
                'add_as_ingredient'       => $request->boolean('add_as_ingredient'),
                'unit_ing_cost'           => $unitIngCost,
            ]);

            // Reemplazar todos los ingredientes en la tabla pivot
            $recipe->ingredients()->delete();
            foreach ($data['ingredients'] as $line) {
                $recipe->ingredients()->create([
                    'ingredient_id' => $line['id'],
                    'quantity_g'    => $line['quantity'],
                ]);
            }

            // Sincronizar/eliminar “receta como ingrediente”
            if ($request->boolean('add_as_ingredient')) {
                Ingredient::updateOrCreate(
                    ['recipe_id' => $recipe->id, 'user_id' => Auth::id()],
                    [
                        'ingredient_name' => $recipe->recipe_name,
                        'price_per_kg'    => $recipe->production_cost_per_kg,
                    ]
                );
            } else {
                Ingredient::where('recipe_id', $recipe->id)
                    ->where('user_id', Auth::id())
                    ->delete();
            }
        });

        return redirect()->route('recipes.index')
            ->with('success', 'Receta actualizada con éxito.');
    }

    /**
     * Eliminar una receta (y sus líneas si falta el cascade).
     */
    public function destroy($id)
    {
        $recipe = Recipe::findOrFail($id);
        $recipe->ingredients()->delete(); // si el cascade no está configurado
        $recipe->delete();

        return redirect()->route('recipes.index')
            ->with('success', 'Receta eliminada con éxito.');
    }

    /**
     * Duplicar una receta (y sus líneas de ingredientes).
     */
    public function duplicate(Recipe $recipe)
    {
        $copy = $recipe->replicate();
        $copy->recipe_name = 'Copia de ' . $recipe->recipe_name;
        $copy->save();

        foreach ($recipe->ingredients as $line) {
            $copy->ingredients()->create([
                'ingredient_id' => $line->ingredient_id,
                'quantity_g'    => $line->quantity_g,
            ]);
        }

        return redirect()
            ->route('recipes.edit', $copy->id)
            ->with('success', 'Receta duplicada. Modifícala y guárdala.');
    }

    // ------------------------------------------------------------------
    // Helpers (lógica de corrección de errores - usar solo overrides guardados)
    // ------------------------------------------------------------------

    private function groupRootId(User $user): int
    {
        return $user->created_by ?? $user->id;
    }

    private function latestGlobalLaborCost(int $rootId): ?LaborCost
    {
        return LaborCost::where('user_id', $rootId)
            ->whereNull('department_id')
            ->orderByDesc('id')
            ->first();
    }

    private function latestDeptOverride(int $rootId, int $departmentId): ?LaborCost
    {
        return LaborCost::where('user_id', $rootId)
            ->where('department_id', $departmentId)
            ->orderByDesc('id')
            ->first();
    }

    private function buildDeptRatesMap(int $rootId, $departments, ?LaborCost $base): array
    {
        $baseShop = (float) ($base->shop_cost_per_min ?? 0);
        $baseExt  = (float) ($base->external_cost_per_min ?? 0);

        // pre-cargar los últimos overrides indexados por department_id
        $overrides = LaborCost::where('user_id', $rootId)
            ->whereNotNull('department_id')
            ->orderByDesc('id')
            ->get()
            ->groupBy('department_id')
            ->map->first();

        $ratesByDept = [];
        foreach ($departments as $d) {
            $ov = $overrides->get($d->id);
            if ($ov && ((float)$ov->shop_cost_per_min > 0 || (float)$ov->external_cost_per_min > 0)) {
                $ratesByDept[$d->id] = [
                    'shop'     => (float) $ov->shop_cost_per_min,
                    'external' => (float) $ov->external_cost_per_min,
                ];
            } else {
                $ratesByDept[$d->id] = [
                    'shop'     => $baseShop,
                    'external' => $baseExt,
                ];
            }
        }

        // fallback/por defecto (sin departamento elegido): solo tarifa global
        $ratesByDept['default'] = [
            'shop'     => $baseShop,
            'external' => $baseExt,
        ];

        return $ratesByDept;
    }

    private function effectiveRatesFor(User $owner, ?Department $dept): array
    {
        $rootId = $this->groupRootId($owner);
        $base   = $this->latestGlobalLaborCost($rootId);
        $baseShop = (float) ($base->shop_cost_per_min ?? 0);
        $baseExt  = (float) ($base->external_cost_per_min ?? 0);

        if (!$dept) {
            return ['shop' => $baseShop, 'external' => $baseExt];
        }

        $ov = $this->latestDeptOverride($rootId, $dept->id);
        if ($ov && ((float)$ov->shop_cost_per_min > 0 || (float)$ov->external_cost_per_min > 0)) {
            return [
                'shop'     => (float) $ov->shop_cost_per_min,
                'external' => (float) $ov->external_cost_per_min,
            ];
        }

        return ['shop' => $baseShop, 'external' => $baseExt];
    }
}
