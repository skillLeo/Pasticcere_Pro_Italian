<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\RecipeCategory;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RecipeCategoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // 1) Construir el “grupo” de IDs que debes ver
        if (is_null($user->created_by)) {
            // Eres un usuario raíz: ves a ti mismo + a cualquiera que hayas creado
            $visibleUserIds = \App\Models\User::where('created_by', $user->id)
                ->pluck('id')
                ->push($user->id)
                ->unique();
        } else {
            // Eres un hijo: te ves a ti mismo + a tu creador
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 2) Obtener categorías de tu grupo O con status = 'Default'
        $categories = RecipeCategory::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                    ->orWhere('status', 'Default');
            })
            ->orderBy('name')
            ->get();

        return view('frontend.recipe_categories.index', compact('categories'));
    }

    /**
     * Guardar una nueva categoría para este usuario.
     */


public function store(Request $request)
{
    $user = Auth::user();

    if (is_null($user->created_by)) {
        $visibleUserIds = User::where('created_by', $user->id)
            ->pluck('id')
            ->push($user->id)
            ->unique();
    } else {
        $visibleUserIds = collect([$user->id, $user->created_by])->unique();
    }

    $data = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('recipe_categories')
                ->where(function ($query) use ($visibleUserIds, $request) {
                    return $query->whereIn('user_id', $visibleUserIds)
                                 ->where('name', $request->name);
                }),
        ],
    ]);

    $data['user_id'] = $user->id;

    RecipeCategory::create($data);

    return back()->with('success', '¡Categoría añadida con éxito!');
}

public function update(Request $request, RecipeCategory $recipeCategory)
{
    $user = Auth::user();

    // guardia: solo registros propios
    if ($recipeCategory->user_id !== $user->id) {
        abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada');
    }

    if (is_null($user->created_by)) {
        $visibleUserIds = User::where('created_by', $user->id)
            ->pluck('id')
            ->push($user->id)
            ->unique();
    } else {
        $visibleUserIds = collect([$user->id, $user->created_by])->unique();
    }

    $data = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('recipe_categories')
                ->where(function ($query) use ($visibleUserIds, $request, $recipeCategory) {
                    return $query->whereIn('user_id', $visibleUserIds)
                                 ->where('name', $request->name);
                })
                ->ignore($recipeCategory->id),
        ],
    ]);

    $recipeCategory->update([
        'name' => $data['name'],
        // user_id sin cambios
    ]);

    return redirect()
        ->route('recipe-categories.index')
        ->with('success', '¡Categoría actualizada con éxito!');
}


    /**
     * Mostrar el formulario de edición (la misma vista, pero con $category precargada).
     */
    public function edit(RecipeCategory $recipeCategory)
    {
        $userId = Auth::id();

        // guardia: solo registros propios
        if ($recipeCategory->user_id !== $userId) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada');
        }

        // solo categorías de este usuario
        $categories = RecipeCategory::where('user_id', $userId)
            ->orderBy('name')
            ->get();

        return view('frontend.recipe_categories.create', [
            'category'   => $recipeCategory,
            'categories' => $categories,
        ]);
    }

    /**
     * Actualizar una categoría existente, limitada al usuario.
     */

    /**
     * Mostrar una sola categoría.
     */
    public function show(RecipeCategory $recipeCategory)
    {
        return view('frontend.recipe_categories.show', compact('recipeCategory'));
    }

    /**
     * Eliminar una categoría — solo si pertenece al usuario.
     */
    public function destroy(RecipeCategory $recipeCategory)
    {
        $userId = Auth::id();

        // guardia: solo registros propios
        if ($recipeCategory->user_id !== $userId) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada');
        }

        $recipeCategory->delete();

        return back()->with('success', '¡Categoría eliminada con éxito!');
    }
}
