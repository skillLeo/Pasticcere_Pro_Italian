<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PastryChef;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PastryChefController extends Controller
{
    /**
     * Muestra el listado de todos los pasteleros visibles para el usuario.
     */
    public function index()
    {
        $user = Auth::user();

        // 1) Construir el grupo de user_id visibles
        if (is_null($user->created_by)) {
            // ROOT user: yo + a quienes he creado
            $visibleUserIds = User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            // CHILD user: yo + quien me ha creado
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }

        // 2) Obtener los pasteleros del grupo o los globales por defecto
        $pastryChefs = PastryChef::with('user')
            ->where(function($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds);
            })
            ->orWhere(function($q) {
                $q->whereNull('user_id')
                  ->where('status', 'Default');
            })
            ->latest()
            ->get();

        return view('frontend.pastry-chefs.index', compact('pastryChefs'));
    }

    /**
     * Muestra el formulario para crear un nuevo pastelero.
     */
    public function create()
    {
        return view('frontend.pastry-chefs.create');
    }

    /**
     * Guarda un nuevo pastelero.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        // Asignar el usuario actual
        $data['user_id'] = Auth::id();

        PastryChef::create($data);

        return redirect()
            ->route('pastry-chefs.index')
            ->with('success', 'Pastelero añadido con éxito!');
    }

    /**
     * Muestra el formulario para editar un pastelero (solo si es de su propiedad).
     */
    public function edit(PastryChef $pastryChef)
    {
        $user = Auth::user();

        // Permitir al super admin o al creador
        if (!$user->hasRole('super') && $pastryChef->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return view('frontend.pastry-chefs.create', compact('pastryChef'));
    }

    /**
     * Actualiza un pastelero existente.
     */
    public function update(Request $request, PastryChef $pastryChef)
    {
        if ($pastryChef->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $pastryChef->update($data);

        return redirect()
            ->route('pastry-chefs.index')
            ->with('success', 'Pastelero actualizado con éxito!');
    }

    /**
     * Muestra los detalles de un pastelero.
     */
    public function show(PastryChef $pastryChef)
    {
        return view('frontend.pastry-chefs.show', compact('pastryChef'));
    }

    /**
     * Elimina un pastelero (solo si es de su propiedad).
     */
    public function destroy(PastryChef $pastryChef)
    {
        if ($pastryChef->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $pastryChef->delete();

        return redirect()
            ->route('pastry-chefs.index')
            ->with('success', 'Pastelero eliminado con éxito!');
    }
}
