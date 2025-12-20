<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EquipmentController extends Controller
{
    /**
     * Muestra el listado del equipo del usuario conectado.
     */
    public function index()
    {
        $user = Auth::user();

        // 1) Construye tu grupo de dos niveles de IDs de usuario
        if (is_null($user->created_by)) {
            // Usuario raíz: tú mismo + cualquiera que hayas creado
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            // Usuario hijo: tú mismo + tu creador
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 2) Obtener equipos: del grupo o con status = 'Default'
        $equipments = Equipment::with('user')
                        ->where(function($q) use ($visibleUserIds) {
                            $q->whereIn('user_id', $visibleUserIds)
                              ->orWhere('status', 'Default');
                        })
                        ->latest()
                           ->get();

        return view('frontend.equipment.index', compact('equipments'));
    }

    /**
     * Muestra el formulario para crear un nuevo equipo.
     */
    public function create()
    {
        return view('frontend.equipment.create');
    }

    /**
     * Guarda un nuevo equipo para este usuario.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
        ]);

        $data['user_id'] = Auth::id();

        Equipment::create($data);

        return redirect()
            ->route('equipment.index')
            ->with('success', '¡Equipo añadido correctamente!');
    }

    /**
     * Muestra el formulario para editar el equipo especificado,
     * solo si pertenece al usuario conectado.
     */
    public function edit(Equipment $equipment)
    {
        if ($equipment->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return view('frontend.equipment.create', compact('equipment'));
    }

    /**
     * Actualiza en almacenamiento el equipo especificado,
     * solo si pertenece al usuario conectado.
     */
    public function update(Request $request, Equipment $equipment)
    {
        if ($equipment->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
        ]);

        $equipment->update($data);

        return redirect()
            ->route('equipment.index')
            ->with('success', '¡Equipo actualizado correctamente!');
    }

    /**
     * Muestra el equipo especificado.
     */
    public function show(Equipment $equipment)
    {
        return view('frontend.equipment.show', compact('equipment'));
    }

    /**
     * Elimina el equipo especificado del almacenamiento,
     * solo si pertenece al usuario conectado.
     */
    public function destroy(Equipment $equipment)
    {
        if ($equipment->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $equipment->delete();

        return redirect()
            ->route('equipment.index')
            ->with('success', '¡Equipo eliminado correctamente!');
    }
}
