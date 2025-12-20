<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\Equipment;
use App\Models\PastryChef;
use App\Models\Production;
use Illuminate\Http\Request;
use App\Models\ProductionDetail;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Chef;
use Symfony\Component\HttpFoundation\Response;

class ProductionController extends Controller
{
    public function getTemplate($id)
    {
        $userId = Auth::id();

        $production = Production::with('details')
            ->where('user_id', $userId)
            ->findOrFail($id);

        $details = $production->details->map(function($d) {
            return [
                'recipe_id'         => $d->recipe_id,
                'chef_id'           => $d->pastry_chef_id,
                'quantity'          => $d->quantity,
                'execution_time'    => $d->execution_time,
                'equipment_ids'     => $d->equipment_ids ? explode(',', $d->equipment_ids) : [],
                'potential_revenue' => $d->potential_revenue,
            ];
        });

        return response()->json([
            'production_name' => $production->production_name,
            'details'         => $details
        ]);
    }

    public function show(Request $request, Production $production)
    {
        // 1) Mapa de equipos
        $equipmentMap = Equipment::pluck('name', 'id')->toArray();

        // 2) Construir desplegable de pasteleros usados aquí
        $allChefs = PastryChef::whereIn(
            'id',
            $production->details()->pluck('pastry_chef_id')->unique()
        )
        ->orderBy('name')
        ->pluck('name', 'id')
        ->toArray();

        // 3) Iniciar consulta
        $detailsQuery = $production
            ->details()
            ->with(['recipe', 'chef']); 
            // asegúrate de que la relación chef() en el modelo ProductionDetail use pastry_chef_id

        // 4) Aplicar filtro por pastelero
        if ($request->filled('chef_id')) {
            $detailsQuery->where('pastry_chef_id', $request->chef_id);
        }

        // 5) Ordenar por pastelero si se solicita
        $sortDir = $request->input('direction', 'asc');
        if ($request->input('sort') === 'chef') {
            $detailsQuery
                ->join('pastry_chefs', 'production_details.pastry_chef_id', '=', 'pastry_chefs.id')
                ->orderBy('pastry_chefs.name', $sortDir)
                ->select('production_details.*');
        }

        // 6) Obtener filas
        $details = $detailsQuery->get();

        // 7) Renderizar
        return view('frontend.production.show', [
            'production'   => $production,
            'equipmentMap' => $equipmentMap,
            'allChefs'     => $allChefs,
            'details'      => $details,
            'selectedChef' => $request->chef_id,
            'sortDir'      => $sortDir,
        ]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        // 1) Determinar grupo: usuarios de nivel superior se ven a sí mismos + sus hijos; los hijos se ven a sí mismos + su creador
        $groupRootId  = $user->created_by ?? $user->id;
        $groupUserIds = User::where('created_by', $groupRootId)
                            ->pluck('id')
                            ->push($groupRootId)
                            ->unique();

        // 2) Cargar todas las producciones para ese grupo
        $productions = Production::with(['details.recipe', 'details.chef', 'user'])
                                 ->whereIn('user_id', $groupUserIds)
                                 ->latest()
                                 ->get();

        // 3) Calcular el ingreso potencial total de todos los detalles
        $totalPotentialRevenue = $productions
            ->flatMap(fn($p) => $p->details)
            ->sum('potential_revenue');

        // 4) Mapa de equipos para visualización y filtros
        $equipmentMap = Equipment::whereIn('user_id', $groupUserIds)
                                 ->pluck('name', 'id')
                                 ->toArray();

        return view('frontend.production.index', compact(
            'productions',
            'equipmentMap',
            'totalPotentialRevenue'
        ));
    }

    public function create()
    {
        $user = Auth::user();
        $groupRootId = $user->created_by ?? $user->id;
        $groupUserIds = \App\Models\User::where('created_by', $groupRootId)
                            ->pluck('id')
                            ->push($groupRootId)
                            ->unique();

        // 1) Recetas (modo coste de trabajo "shop" dentro de tu grupo)
        $recipes = \App\Models\Recipe::where('labor_cost_mode', 'shop')
                        ->whereIn('user_id', $groupUserIds)
                        ->get();

        // 2) Construir IDs de usuarios visibles (para pasteleros y equipos)
        if (is_null($user->created_by)) {
            $visibleUserIds = \App\Models\User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 3) Pasteleros: del grupo O por defecto
        $chefs = PastryChef::with('user')
            ->where(function($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds);
            })
            ->orWhere(function($q) {
                $q->whereNull('user_id')
                  ->where('status', 'Default');
            })
            ->orderBy('name')
            ->get();

        // 4) Equipos: del grupo O por defecto
        $equipments = Equipment::with('user')
            ->where(function($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhere('status', 'Default');
            })
            ->orderBy('name')
               ->get();

        // 5) MODELOs (producciones guardadas como MODELO en tu grupo)
        $templates = \App\Models\Production::where('save_template', true)
                        ->whereIn('user_id', $groupUserIds)
                        ->pluck('production_name', 'id');

        return view('frontend.production.create', compact(
            'recipes',
            'chefs',
            'equipments',
            'templates'
        ));
    }

    public function store(Request $request)
    {
        $templateAction = $request->input('template_action');
        $isTemplate = in_array($templateAction, ['template', 'both']);

        $rules = [
            'production_name'   => $isTemplate ? 'required|string|max:255' : 'nullable|string|max:255',
            'production_date'   => 'required|date',
            'template_action'   => 'nullable|in:none,template,both',
            'recipe_id'         => 'required|array',
            'pastry_chef_id'    => 'required|array',
            'quantity'          => 'required|array',
            'execution_time'    => 'required|array',
            'equipment_ids'     => 'required|array',
            'potential_revenue' => 'required|array',
            'total_revenue'     => 'required|numeric|min:0',
        ];

        $data = $request->validate($rules);

        $saveTemplate = $isTemplate;

        $production = Production::create([
            'production_name'         => $data['production_name'],
            'production_date'         => $data['production_date'],
            'total_potential_revenue' => $data['total_revenue'],
            'save_template'           => $saveTemplate,
            'user_id'                 => Auth::id(),
        ]);

        foreach ($data['recipe_id'] as $i => $recipeId) {
            ProductionDetail::create([
                'production_id'     => $production->id,
                'recipe_id'         => $recipeId,
                'pastry_chef_id'    => $data['pastry_chef_id'][$i],
                'quantity'          => $data['quantity'][$i],
                'execution_time'    => $data['execution_time'][$i],
                'equipment_ids'     => implode(',', $data['equipment_ids'][$i] ?? []),
                'potential_revenue' => $data['potential_revenue'][$i],
                'user_id'           => Auth::id(),
            ]);
        }

        return redirect()->route('production.create')
            ->with('success', 'Producción guardada con éxito.');
    }

    public function edit($id)
    {
        $user = Auth::user();
        $userId = $user->id;

        // 1) Cargar la producción (con detalles) de este usuario
        $production = Production::with('details')
            ->where('user_id', $userId)
            ->findOrFail($id);

        // 2) Recetas (modo coste de trabajo "shop" propiedad de este usuario)
        $recipes = Recipe::where('labor_cost_mode', 'shop')
            ->where('user_id', $userId)
            ->get();

        // 3) Construir IDs de usuarios visibles (para pasteleros y equipos)
        if (is_null($user->created_by)) {
            $visibleUserIds = \App\Models\User::where('created_by', $user->id)
                ->pluck('id')
                ->push($user->id)
                ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 4) Pasteleros: del grupo O por defecto
        $chefs = PastryChef::with('user')
            ->where(function($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds);
            })
            ->orWhere(function($q) {
                $q->whereNull('user_id')
                  ->where('status', 'Default');
            })
            ->orderBy('name')
            ->get();

        // 5) Equipos: del grupo O por defecto
        $equipments = Equipment::with('user')
            ->where(function($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhere('status', 'Default');
            })
            ->orderBy('name')
               ->get();

        // 6) MODELOs (producciones guardadas como MODELO por este usuario)
        $templates = Production::where('save_template', true)
            ->where('user_id', $userId)
            ->pluck('production_name', 'id');

        return view('frontend.production.create', compact(
            'production',
            'recipes',
            'chefs',
            'equipments',
            'templates'
        ));
    }

    public function update(Request $request, $id)
    {
        $userId = Auth::id();

        $production = Production::where('user_id', $userId)
            ->findOrFail($id);

        $templateAction = $request->input('template_action');
        $isTemplate = in_array($templateAction, ['template', 'both']);

        $rules = [
            'production_name'   => $isTemplate ? 'required|string|max:255' : 'nullable|string|max:255',
            'production_date'   => 'required|date',
            'template_action'   => 'required|in:none,template,both',
            'recipe_id'         => 'required|array',
            'pastry_chef_id'    => 'required|array',
            'quantity'          => 'required|array',
            'execution_time'    => 'required|array',
            'equipment_ids'     => 'required|array',
            'potential_revenue' => 'required|array',
            'total_revenue'     => 'required|numeric|min:0',
        ];

        $data = $request->validate($rules);

        $production->update([
            'production_name'         => $data['production_name'],
            'production_date'         => $data['production_date'],
            'total_potential_revenue' => $data['total_revenue'],
            'save_template'           => $isTemplate,
        ]);

        $production->details()->delete();

        foreach ($data['recipe_id'] as $i => $recipeId) {
            ProductionDetail::create([
                'production_id'     => $production->id,
                'recipe_id'         => $recipeId,
                'pastry_chef_id'    => $data['pastry_chef_id'][$i],
                'quantity'          => $data['quantity'][$i],
                'execution_time'    => $data['execution_time'][$i],
                'equipment_ids'     => implode(',', $data['equipment_ids'][$i] ?? []),
                'potential_revenue' => $data['potential_revenue'][$i],
                'user_id'           => Auth::id(),
            ]);
        }

        return redirect()->route('production.index')
            ->with('success', 'Producción actualizada con éxito.');
    }

    public function destroy($id)
    {
        $production = Production::where('user_id', Auth::id())
            ->findOrFail($id);

        $production->delete();

        return redirect()->route('production.index')
            ->with('success', 'Producción eliminada con éxito.');
    }
}
