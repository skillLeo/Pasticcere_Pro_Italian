{{-- resources/views/frontend/news/index.blade.php --}}

@extends('frontend.layouts.app')

@section('title', 'Noticias & Blog')

@section('content')
<div class="container py-5">

    <!-- Sección Últimas Novedades -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center text-dark">Últimas novedades</h2>
            <p class="text-center text-muted">Aquí encontrarás todas las novedades para el crecimiento de tu negocio.</p>
        </div>
    </div>

    <div class="row gy-4">
        @foreach($news as $item)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-lg rounded-3 overflow-hidden">

                    {{-- Sección de imagen --}}
                    @if ($item->image)
                        <img src="{{ asset('storage/' . $item->image) }}" 
                             alt="{{ $item->title }}" 
                             class="card-img-top"
                             style="object-fit: cover; height: 220px;">
                    @endif

                    {{-- Título + Insignia --}}
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #041930; color: #e2ae76;">
                        <h5 class="mb-0 flex-grow-1 text-truncate" style="background-color: #041930; color: #e2ae76;" title="{{ $item->title }}">
                            {{ $item->title }}
                        </h5>
                        <span class="badge bg-warning text-dark ms-2">{{ $item->created_at->diffForHumans() }}</span>
                    </div>

                    {{-- Contenido con auto-enlaces --}}
                    <div class="card-body">
                        <div class="text-muted" style="white-space: pre-wrap;">
                            {!! preg_replace(
                                '~(https?://[^\s<]+)~',
                                '<a href="$1" class="text-primary" target="_blank" rel="noopener noreferrer">$1</a>',
                                e(Str::limit($item->content, 150))
                            ) !!}
                            @if (strlen($item->content) > 150) … @endif
                        </div>
                    </div>

                    {{-- Pie de tarjeta --}}
                    <div class="card-footer text-end">
                        <a href="{{ route('news.show', $item->id) }}" 
                           class="btn btn-sm"
                           style="background-color:#e2ae76; color:#041930; border-color:#e2ae76;">
                            Leer más
                        </a>
                    </div>
                </div>
            </div>
        @endforeach

        <!-- Estado vacío si no hay artículos -->
        @if($news->isEmpty())
            <div class="col-12 text-center text-muted">
                <p>No se ha encontrado ningún artículo.</p>
            </div>
        @endif
    </div>

</div>
@endsection
