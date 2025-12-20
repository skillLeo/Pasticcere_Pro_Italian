@extends('frontend.layouts.app')

@section('title','Categorías de Ingresos')

@section('content')
<div class="container py-5 px-md-5">
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card shadow-sm border-success">
        <div class="card-header" style="background:#041930;color:#e2ae76;">
          <strong class="mb-0">
            @isset($editingCategory) Editar categoría @else Nueva categoría @endisset
          </strong>
        </div>
        <div class="card-body">
          <form method="POST"
                action="@isset($editingCategory) {{ route('income-categories.update', $editingCategory) }} @else {{ route('income-categories.store') }} @endisset"
                class="row g-3 needs-validation" novalidate>
            @csrf
            @isset($editingCategory) @method('PUT') @endisset

            <div class="col-12">
              <label class="form-label fw-semibold">Nombre</label>
              <input type="text" name="name" required
                     value="{{ old('name', $editingCategory->name ?? '') }}"
                     class="form-control form-control-lg @error('name') is-invalid @enderror">
              <div class="invalid-feedback">{{ $errors->first('name', 'Introduce el nombre.') }}</div>
            </div>

            <div class="col-12 text-end">
              <button type="submit" class="btn btn-gold-save btn-lg">
                @isset($editingCategory) Actualizar @else Guardar @endisset
              </button>
              @isset($editingCategory)
                <a href="{{ route('income-categories.index') }}" class="btn btn-deepblue btn-lg ms-2">Cancelar</a>
              @endisset
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header" style="background:#041930;color:#e2ae76;">
          <h5 class="mb-0 fw-bold">Categorías de Ingresos</h5>
        </div>
        <div class="card-body table-responsive">
          <table class="table table-hover align-middle text-center mb-0" data-page-length="25">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse($categories as $cat)
                <tr>
                  <td class="fw-semibold">{{ $cat->name }}</td>
                  {{-- <td>
                    @if(is_null($cat->user_id))
                      <span class="badge bg-secondary">Globale</span>
                    @else
                      <span class="badge bg-primary">Personale</span>
                    @endif
                  </td> --}}
                  <td>
                    @if(!is_null($cat->user_id))
                      <a href="{{ route('income-categories.edit', $cat) }}" class="btn btn-sm btn-gold me-1">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form class="d-inline" method="POST"
                            action="{{ route('income-categories.destroy', $cat) }}"
                            onsubmit="return confirm('¿Eliminar la categoría?');">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-red">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted">No hay categorías.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .btn-gold-save{ border:1px solid #e2ae76!important;color:#041930!important;background-color:#e2ae76!important; }
  .btn-gold-save:hover{ background-color:#d89d5c!important;color:#fff!important; }
  .btn-deepblue{ border:1px solid #041930!important;color:#041930!important;background:transparent!important; }
  .btn-deepblue:hover{ background:#041930!important;color:#fff!important; }
  .btn-red{ border:1px solid red!important;color:red!important;background:transparent!important; }
  .btn-red:hover{ background:red!important;color:#fff!important; }
</style>
<style>
  /* Alinear a la izquierda todas las celdas del cuerpo de la tabla por defecto */
  table tbody td {
    text-align: left !important;
    vertical-align: middle !important;
  }

  /* Mantener el encabezado de la tabla alineado a la izquierda (excepto acciones) */
  table thead th {
    text-align: left !important;
    vertical-align: middle !important;
  }

  /* La columna de acciones debe permanecer centrada */
  table thead th:last-child,
  table tbody td:last-child {
    text-align: center !important;
    width: 120px; /* opcional, mantiene los botones compactos */
  }
</style>

@endsection
