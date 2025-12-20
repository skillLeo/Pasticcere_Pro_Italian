<?php

namespace App\Http\Controllers;

use App\Models\IncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IncomeCategoryController extends Controller
{
    public function index()
    {
        $categories = IncomeCategory::visibleTo(Auth::user())
            ->orderByRaw('user_id IS NULL DESC') // mostrar globales primero (NULL va primero)
            ->orderBy('name')
            ->get();

        return view('frontend.income_categories.index', compact('categories'));
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $ownerId = IncomeCategory::ownerIdFor(Auth::user());

    // 🔹 Comprobar nombre duplicado para este propietario o global
    $exists = IncomeCategory::where('name', $data['name'])
        ->where(function ($q) use ($ownerId) {
            $q->where('user_id', $ownerId)
              ->orWhereNull('user_id'); // si también quieres bloquear el mismo nombre que el global
        })
        ->exists();

    if ($exists) {
        return back()
            ->withErrors(['name' => 'Esta categoría ya existe.'])
            ->withInput();
    }

    IncomeCategory::create([
        'name'    => $data['name'],
        'user_id' => $ownerId,
    ]);

    return back()->with('success', 'Categoría guardada.');
}

    public function edit(IncomeCategory $income_category)
    {
        // Solo permitir edición si pertenece al propietario del tenant o es global y quieres permitirlo.
        // Aquí: las filas globales (user_id NULL) son de solo lectura para usuarios no super.
        if (is_null($income_category->user_id)) {
            abort(Response::HTTP_FORBIDDEN, 'Esta categoría global no se puede modificar.');
        }

        $ownerId = IncomeCategory::ownerIdFor(Auth::user());
        if ((int) $income_category->user_id !== $ownerId) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $categories = IncomeCategory::visibleTo(Auth::user())
            ->orderByRaw('user_id IS NULL DESC')
            ->orderBy('name')
            ->get();

        return view('frontend.income_categories.index', [
            'categories'       => $categories,
            'editingCategory'  => $income_category,
        ]);
    }

  public function update(Request $request, IncomeCategory $income_category)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $ownerId = IncomeCategory::ownerIdFor(Auth::user());

    // Solo el propietario del tenant puede editar su propia categoría
    if ((int) $income_category->user_id !== $ownerId) {
        abort(Response::HTTP_FORBIDDEN);
    }

    // 🔹 Comprobar nombre duplicado excluyendo esta categoría
    $exists = IncomeCategory::where('name', $data['name'])
        ->where('id', '<>', $income_category->id)
        ->where(function ($q) use ($ownerId) {
            $q->where('user_id', $ownerId)
              ->orWhereNull('user_id');
        })
        ->exists();

    if ($exists) {
        return back()
            ->withErrors(['name' => 'Esta categoría ya existe.'])
            ->withInput();
    }

    $income_category->update(['name' => $data['name']]);

    return back()->with('success', 'Categoría actualizada.');
}


    public function destroy(IncomeCategory $income_category)
    {
        // Bloquear eliminación de categorías globales (user_id NULL)
        if (is_null($income_category->user_id)) {
            abort(Response::HTTP_FORBIDDEN, 'No puedes eliminar categorías globales.');
        }

        $ownerId = IncomeCategory::ownerIdFor(Auth::user());
        if ((int) $income_category->user_id !== $ownerId) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $income_category->delete();
        return back()->with('success', 'Categoría eliminada.');
    }
}
