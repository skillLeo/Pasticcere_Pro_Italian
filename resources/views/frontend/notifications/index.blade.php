@extends('frontend.layouts.app')

@section('title', 'Notificaciones')

@section('content')
<div class="container py-5">
    <h4 class="mb-4">Notificaciones</h4>

    @foreach($notifications as $notification)
        <div class="alert alert-info">
            <strong>{{ $notification->title }}</strong>
            <p>{{ $notification->message }}</p>
            <p class="text-muted">{{ $notification->created_at->diffForHumans() }}</p>

            <!-- Botón Marcar como leída -->
            <form action="{{ route('notifications.markAsRead', $notification->id) }}" method="POST">
                @csrf
                @method('POST')
                <button type="submit" class="btn btn-sm btn-primary">Marcar como leída</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
