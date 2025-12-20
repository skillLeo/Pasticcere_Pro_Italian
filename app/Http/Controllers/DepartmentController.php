<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    /**
     * Muestra el listado de departamentos para el usuario conectado.
     */
    public function index()
    {
        $user = Auth::user();

        // 1) Grupo de usuarios visibles (tú + hijos o creador)
        if (is_null($user->created_by)) {
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 2) Cargar departamentos de esos usuarios o globales (user_id NULL)
        $departments = Department::with('user')
                            ->where(function($q) use ($visibleUserIds) {
                                $q->whereIn('user_id', $visibleUserIds)
                                  ->orWhereNull('user_id');
                            })
                            ->latest()
                               ->get();

        return view('frontend.departments.index', compact('departments'));
    }

    /**
     * Muestra el formulario para crear un nuevo departamento.
     */
    public function create()
    {
        return view('frontend.departments.create');
    }

    /**
     * Guarda un nuevo departamento para el usuario.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $data['user_id'] = Auth::id();

        Department::create($data);

        return redirect()
            ->route('departments.index')
            ->with('success', '¡Departamento creado correctamente!');
    }

    /**
     * Muestra el formulario para editar un departamento existente.
     */
    public function edit(Department $department)
    {
        $user = Auth::user();

        // Autorización: propietario o roles especiales
        if (
            $department->user_id !== $user->id &&
            !$user->hasRole('admin') &&
            !$user->hasRole('super') &&
            !$user->hasRole('master')
        ) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        return view('frontend.departments.create', compact('department'));
    }

    /**
     * Actualiza el departamento (solo si es propiedad del usuario).
     */
    public function update(Request $request, Department $department)
    {
        if ($department->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizzata.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $department->update($data);

        return redirect()
            ->route('departments.index')
            ->with('success', '¡Departamento actualizado correctamente!');
    }

    /**
     * Muestra los detalles de un departamento.
     */
    public function show(Department $department)
    {
        return view('frontend.departments.show', compact('department'));
    }

    /**
     * Elimina un departamento (solo si es propiedad del usuario).
     */
    public function destroy(Department $department)
    {
        if ($department->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizzata.');
        }

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', '¡Departamento eliminado correctamente!');
    }
}
