<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        return view('frontend.user-management.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('frontend.user-management.permissions.create', [
            'isEdit' => false
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name'
        ], [
            'name.required' => 'El nombre del permiso es obligatorio.',
            'name.unique'   => 'Este permiso ya existe.'
        ]);

        Permission::create(['name' => $request->name]);

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permiso creado con éxito.');
    }

    public function edit(Permission $permission)
    {
        return view('frontend.user-management.permissions.create', [
            'isEdit'     => true,
            'permission' => $permission
        ]);
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => "required|unique:permissions,name,{$permission->id}"
        ], [
            'name.required' => 'El nombre del permiso es obligatorio.',
            'name.unique'   => 'Este permiso ya existe.'
        ]);

        $permission->update(['name' => $request->name]);

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permiso actualizado con éxito.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permiso eliminado con éxito.');
    }
}
