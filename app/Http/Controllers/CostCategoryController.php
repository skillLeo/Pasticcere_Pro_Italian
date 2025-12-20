<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CostCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CostCategoryController extends Controller
{
    /**
     * Muestra la lista de las categorías de costo para el usuario autenticado.
     */
    public function index()
    {
        $user = Auth::user();

        // 1) Grupo de usuarios visibles
        if (is_null($user->created_by)) {
            // Usuario raíz → tú mismo + posibles hijos directos
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            // Usuario hijo → tú mismo + tu creador
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 2) Cargar categorías de estos usuarios o globales (user_id NULL)
        $categories = CostCategory::with('user')
                         ->where(function($q) use ($visibleUserIds) {
                             $q->whereIn('user_id', $visibleUserIds)
                               ->orWhereNull('user_id');
                         })
                         ->orderBy('name')
                         ->get();

        return view('frontend.categories.index', compact('categories'));
    }

    /**
     * El create redirige al índice: el formulario está incluido en index.
     */
    public function create()
    {
        return redirect()->route('cost_categories.index');
    }

    /**
     * Muestra una sola categoría.
     */
    public function show(CostCategory $costCategory)
    {
        return view('frontend.categories.show', compact('costCategory'));
    }

    /**
     * Guarda una nueva categoría para el usuario.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $data['user_id'] = Auth::id();

        CostCategory::create($data);

        return redirect()
            ->route('cost_categories.index')
            ->with('success', 'Categoría añadida con éxito!');
    }

    /**
     * Muestra el formulario de edición (el formulario está incluido en index).
     */
    public function edit(CostCategory $cost_category)
    {
        if ($cost_category->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        $categories = CostCategory::where('user_id', Auth::id())
                                  ->latest()
                                  ->get();

        return view('frontend.categories.index', [
            'category'   => $cost_category,
            'categories' => $categories,
        ]);
    }

    /**
     * Actualiza la categoría (solo si es propiedad del usuario).
     */
    public function update(Request $request, CostCategory $cost_category)
    {
        if ($cost_category->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $cost_category->update($data);

        return redirect()
            ->route('cost_categories.index')
            ->with('success', 'Categoría actualizada con éxito!');
    }

    /**
     * Elimina la categoría (solo si es propiedad del usuario).
     */
    public function destroy(CostCategory $cost_category)
    {
        if ($cost_category->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        $cost_category->delete();

        return redirect()
            ->route('cost_categories.index')
            ->with('success', 'Categoría eliminada con éxito!');
    }
}
