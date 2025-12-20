<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Recipe;               // ← también se necesitan las recetas

class IngredientController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // 1) Cálculo de IDs de usuarios visibles
        if (is_null($user->created_by)) {
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 2) Obtengo ingredientes + posible relación recipe
        $ingredients = Ingredient::with('recipe')
                                 ->whereIn('user_id', $visibleUserIds)
                                 ->get();

        return view('frontend.ingredients.index', compact('ingredients'));
    }

    public function create()
    {
        return view('frontend.ingredients.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // validación
        $data = $request->validate([
            'ingredient_name' => [
                'required','string','max:255',
                Rule::unique('ingredients')->where(fn($q) =>
                    $q->where('user_id', $user->id)
                      ->where('ingredient_name', $request->ingredient_name)
                ),
            ],
            'price_per_kg'    => 'required|numeric|min:0',
        ]);

        // añado user_id
        $data['user_id'] = $user->id;

        // creo el ingrediente
        $ingredient = Ingredient::create($data);

        // si es una llamada AJAX (modal “añadir ingrediente”)
        if ($request->expectsJson()) {
            return response()->json($ingredient, 201);
        }

        return back()->with('success', 'Ingrediente guardado con éxito.');
    }

    public function show(Ingredient $ingredient)
    {
        abort_unless($ingredient->user_id === Auth::id(), 403);
        return view('frontend.ingredients.show', compact('ingredient'));
    }

    public function edit(Ingredient $ingredient)
    {
        abort_unless($ingredient->user_id === Auth::id(), 403);
        return view('frontend.ingredients.create', compact('ingredient'));
    }

      // ... keep your other actions (index/create/show/edit/store/destroy) ...

    public function update(Request $request, Ingredient $ingredient)
    {
        $user = Auth::user();
        abort_unless($ingredient->user_id === $user->id, 403);

        $data = $request->validate([
            'ingredient_name' => [
                'required','string','max:255',
                Rule::unique('ingredients')
                    ->where(fn($q) => $q->where('user_id', $user->id))
                    ->ignore($ingredient->id),
            ],
            'price_per_kg' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($ingredient, $data, $user) {
            // 1) Actualizar ingrediente base
            $ingredient->update($data);

            // 2) Si este ingrediente es una “receta-como-ingrediente”, sincronizar el €/kg base de la receta
            if ($ingredient->recipe_id) {
                Recipe::where('id', $ingredient->recipe_id)
                      ->update(['production_cost_per_kg' => $data['price_per_kg']]);
            }

            // 3) En cascada: cualquier receta que use ESTE ingrediente se recalcula.
            //    Si esa receta también está guardada como ingrediente, se actualiza el precio
            //    del ingrediente al unit_ing_cost de la receta y se sigue la cascada hacia arriba.
            $visited = []; // evitar ciclos
            $this->cascadeFromIngredient($ingredient, $visited);
        });

        return redirect()
            ->route('ingredients.index')
            ->with('success', 'Ingrediente actualizado y costes recalculados en cascada.');
    }

    /**
     * Recalcular todas las recetas que usan $ingredient; si alguna receta tiene una fila de ingrediente
     * vinculada, actualizar el precio de ese ingrediente al unit_ing_cost de la receta y recursar hacia arriba.
     */
    private function cascadeFromIngredient(Ingredient $ingredient, array &$visited): void
    {
        if (isset($visited[$ingredient->id])) {
            return;
        }
        $visited[$ingredient->id] = true;

        // encontrar recetas que incluyen este ingrediente
        $recipes = Recipe::whereHas('ingredients', function ($q) use ($ingredient) {
                $q->where('ingredient_id', $ingredient->id);
            })
            ->with(['ingredients.ingredient']) // necesitamos todos los precios de los ingredientes para recalcular
            ->get();

        foreach ($recipes as $recipe) {
            // Recalcular unit_ing_cost (misma lógica que en tu lista de recetas)
            $this->recalcAndPersistRecipeUnitIngCost($recipe);

            // Si esta receta también está guardada como ingrediente, actualizar el precio de ese ingrediente
            // para QUE COINCIDA con el unit_ing_cost de la receta (tu “Costo ingr.” mostrado en la lista)
            $linkedIng = Ingredient::where('recipe_id', $recipe->id)
                                   ->where('user_id', $recipe->user_id)
                                   ->first();

            if ($linkedIng) {
                // Importante: establecer el precio del ingrediente al coste de ingrediente unitario de la receta
                $linkedIng->update(['price_per_kg' => $recipe->unit_ing_cost]);

                // Recurre: las recetas que usan este ingrediente actualizado también deben recalcularse
                $this->cascadeFromIngredient($linkedIng, $visited);
            }
        }
    }

    /**
     * Calcula batch ingredient cost y unit_ing_cost exactamente como en el índice de recetas y lo guarda.
     * - batchIngCost = sum(quantity_g/1000 * ingredient.price_per_kg)
     * - unitIngCost depende de sell_mode:
     *     piece: batchIngCost / total_pieces (>=1)
     *     kg:    batchIngCost / (recipe_weight>0 ? recipe_weight/1000 : sumQty/1000)
     */
    private function recalcAndPersistRecipeUnitIngCost(Recipe $recipe): void
    {
        $recipe->loadMissing('ingredients.ingredient');

        $batchIngCost = 0.0;
        $sumWeightG   = 0.0;

        foreach ($recipe->ingredients as $line) {
            $qtyG    = (float) $line->quantity_g;
            $priceKg = (float) ($line->ingredient->price_per_kg ?? 0);
            $sumWeightG   += $qtyG;
            $batchIngCost += ($qtyG / 1000.0) * $priceKg;
        }
        $batchIngCost = round($batchIngCost, 2);

        if ($recipe->sell_mode === 'piece') {
            $pcs = $recipe->total_pieces > 0 ? $recipe->total_pieces : 1;
            $unitIngCost = round($batchIngCost / $pcs, 2);
        } else {
            $wLossG = (float) ($recipe->recipe_weight ?? 0);
            if ($wLossG <= 0) { $wLossG = $sumWeightG; }
            $kg = $wLossG > 0 ? ($wLossG / 1000.0) : 1;
            $unitIngCost = round($batchIngCost / $kg, 2);
        }

        // Guardar ambos para que la lista y la sincronización con ingredientes sean fiables
        $recipe->update([
            'unit_ing_cost'   => $unitIngCost,
            // mantiene esto si lo usas en otro sitio:
            // 'batch_ing_cost'   => $batchIngCost,  // solo si tienes esta columna
        ]);
    }

    public function destroy(Ingredient $ingredient)
    {
        abort_unless($ingredient->user_id === Auth::id(), 403);

        // si estaba vinculado a una receta pivot, desligar
        $ingredient->recipe()->dissociate(); // establece recipe_id = null
        $ingredient->delete();

        return redirect()
            ->route('ingredients.index')
            ->with('success', 'Ingrediente eliminado con éxito.');
    }
}
