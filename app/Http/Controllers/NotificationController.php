<?php
// En el NotificationController

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // Obtener notificaciones no leídas para el usuario autenticado
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())  // Filtrar por ID del usuario autenticado
            ->where('is_read', false)  // Obtener solo notificaciones no leídas
            ->latest()
            ->get();

        return view('frontend.notifications.index', compact('notifications'));
    }

    // Marcar una notificación específica como leída
    // En NotificationController.php

    // En NotificationController.php

    public function markAsRead($notificationId)
    {
        // Asegurarse de que la notificación pertenece al usuario autenticado
        $notification = Notification::where('user_id', Auth::id())
                                    ->where('id', $notificationId)
                                    ->firstOrFail();  // Si la notificación no existe, lanzar error
        
        // Marcar como leída y actualizar estado
        $notification->update(['is_read' => true]);

        // Redirigir a la página de blogs
        return redirect()->route('blogs');
    }



    // En NotificationController.php

    public function markAllAsRead()
    {
        // Marcar todas las notificaciones no leídas del usuario autenticado como leídas
        Notification::where('user_id', Auth::id())
                    ->where('is_read', false)  // Solo marcar notificaciones no leídas
                    ->update(['is_read' => true]);

        // Redirigir a la página de blogs
        return redirect()->route('blogs');
    }


    // Obtener notificaciones no leídas para el usuario autenticado
    public function unread(Request $request)
    {
        $userId = Auth::id();
        $notifications = Notification::where('user_id', $userId)  // Asegurarse de que las notificaciones sean del usuario autenticado
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'message', 'created_at']);

        return response()->json([
            'count' => $notifications->count(),
            'items' => $notifications->map(fn($n) => [
                'id'      => $n->id,
                'title'   => $n->title,
                'message' => $n->message,
                'time'    => $n->created_at->diffForHumans(),
            ]),
        ]);
    }
}
