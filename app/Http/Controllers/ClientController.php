<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    /**
     * Mostrar un listado de los clientes del usuario autenticado.
     */
    public function index()
    {
        $user = Auth::user();
    
        // 1) Construir el grupo de dos niveles de IDs de usuario
        if (is_null($user->created_by)) {
            // Usuario raíz → ve a sí mismo y a cualquiera que haya creado
            $visibleUserIds = \App\Models\User::where('created_by', $user->id)
                                  ->pluck('id')
                                  ->push($user->id)
                                  ->unique();
        } else {
            // Usuario hijo → se ve a sí mismo y a su creador
            $visibleUserIds = collect([$user->id, $user->created_by])->unique();
        }
    
        // 2) Obtener clientes pertenecientes a esos usuarios O clientes globales (user_id ES NULL)
        $clients = Client::with('user')
            ->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('user_id', $visibleUserIds)
                  ->orWhereNull('user_id');
            })
            ->latest()
            ->get(); // ✅ no más paginate()

    
        return view('frontend.clients.index', compact('clients'));
    }
    
    /**
     * Mostrar el formulario para crear un nuevo cliente.
     */
    public function create()
    {
        return view('frontend.clients.create');
    }

    /**
     * Almacenar un nuevo cliente en el sistema.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:50',
            'email'    => 'nullable|email|max:255',
            'notes'    => 'nullable|string',
        ]);

        // registrar el ID del usuario actual
        $data['user_id'] = Auth::id();

        Client::create($data);

        return redirect()
            ->route('clients.index')
            ->with('success', 'Cliente creado con éxito.');
    }

    /**
     * Mostrar el cliente especificado (solo si pertenece al usuario).
     */
    public function show(Client $client)
    {
        if ($client->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        return view('frontend.clients.show', compact('client'));
    }

    /**
     * Mostrar el formulario para editar el cliente especificado.
     */
    public function edit(Client $client)
    {
        if ($client->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        return view('frontend.clients.create', compact('client'));
    }

    /**
     * Actualizar el cliente especificado en el sistema.
     */
    public function update(Request $request, Client $client)
    {
        if ($client->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:50',
            'email'    => 'nullable|email|max:255',
            'notes'    => 'nullable|string',
        ]);

        $client->update($data);

        return redirect()
            ->route('clients.index')
            ->with('success', 'Cliente actualizado con éxito.');
    }

    /**
     * Eliminar el cliente especificado del sistema.
     */
    public function destroy(Client $client)
    {
        if ($client->user_id !== Auth::id()) {
            abort(Response::HTTP_FORBIDDEN, 'Operación no autorizada.');
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', 'Cliente eliminado con éxito.');
    }
}
