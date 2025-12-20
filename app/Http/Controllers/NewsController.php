<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\NewsNotificationCreated;

class NewsController extends Controller
{
    /**
     * Muestra todas las noticias activas en la página de “news”.
     */
    public function index()
    {
        // Recupera todas las noticias activas, en orden descendente
        $news = News::latest()
            ->where('is_active', true)
            ->get();

        return view('frontend.news.index', compact('news'));
    }

    /**
     * Igual que index(), pero renderiza la vista “blogs”.
     */
    public function blogs()
    {
        $news = News::latest()
            ->where('is_active', true)
            ->get();

        return view('frontend.news.blogs', compact('news'));
    }

    /**
     * Muestra el formulario para crear una nueva noticia.
     */
    public function create()
    {
        return view('frontend.news.create');
    }

    /**
     * Guarda una nueva noticia.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'event_date' => 'required|date',
            'image'      => 'nullable|image|max:95120', // aproximadamente 95 MB
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')
                                     ->store('news_images', 'public');
        }

        $news = News::create($data);

        // (difusión de notificaciones opcional)
        // event(new NewsNotificationCreated($news));

        return redirect()
            ->route('news.index')
            ->with('success', 'Noticia creada con éxito.');
    }

    /**
     * Muestra una noticia individual.
     */
    public function show($id)
    {
        $news = News::findOrFail($id);
        return view('frontend.news.show', compact('news'));
    }

    /**
     * Muestra el formulario para editar una noticia existente.
     */
    public function edit(News $news)
    {
        return view('frontend.news.create', compact('news'));
    }

    /**
     * Actualiza la noticia especificada.
     */
    public function update(Request $request, News $news)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'event_date' => 'required|date',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:20048',
        ]);

        if ($request->hasFile('image')) {
            // Si deseas borrar la imagen anterior: Storage::disk('public')->delete($news->image);
            $data['image'] = $request->file('image')
                                     ->store('news_images', 'public');
        }

        $news->update($data);

        return redirect()
            ->route('news.index')
            ->with('success', 'Noticia actualizada con éxito.');
    }

    /**
     * Elimina la noticia especificada y sus notificaciones.
     */
    public function destroy(News $news)
    {
        // 1) Elimina todas las notificaciones relacionadas
        $news->notifications()->delete();

        // 2) Elimina la noticia
        $news->delete();

        return redirect()
            ->route('news.index')
            ->with('success', 'Noticia y notificaciones relacionadas eliminadas con éxito.');
    }
}
