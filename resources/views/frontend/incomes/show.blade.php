@extends('frontend.layouts.app')

@section('title', 'Ingreso: €' . number_format($income->amount, 2))

@section('content')
<div class="container py-5 px-md-5">
  <div class="card border-primary shadow-lg rounded-3 overflow-hidden">
    <!-- Encabezado -->
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-currency-euro fs-4 me-2" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        Ingreso del {{ $income->date->format('Y-m-d') }}
      </h5>
    </div>

    <div class="card-body">
      <!-- Detalles -->
      <div class="row row-cols-1 row-cols-md-2 g-4 mb-4" style="width: 70%;">
        <div class="col">
          <h6 class="text-uppercase text-muted small mb-1">Fecha</h6>
          <p class="fs-5 mb-0">{{ $income->date->format('Y-m-d') }}</p>
        </div>
        <div class="col">
          <h6 class="text-uppercase text-muted small mb-1">Importe</h6>
          <p class="fs-5 mb-0">€{{ number_format($income->amount, 2) }}</p>
        </div>
        <div class="col">
          <h6 class="text-uppercase text-muted small mb-1">Última actualización</h6>
          <p class="fs-5 mb-0">{{ optional($income->updated_at)?->format('Y-m-d H:i') ?? '—' }}</p>
        </div>
      </div>

      <hr class="border-secondary">

      <!-- Botones de acción -->
      <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('incomes.edit', $income) }}" class="btn btn-gold btn-lg">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="{{ route('incomes.index') }}" class="btn btn-deepblue btn-lg">
          <i class="bi bi-arrow-left me-1"></i>Volver al listado
        </a>
        <form action="{{ route('incomes.destroy', $income) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este ingreso?');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-red btn-lg">
            <i class="bi bi-trash me-1"></i>Eliminar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

<style>
  .btn-gold {
    border: 1px solid #e2ae76 !important;
    color: #e2ae76 !important;
    background-color: transparent !important;
  }
  .btn-gold:hover {
    background-color: #e2ae76 !important;
    color: white !important;
  }
  .btn-deepblue {
    border: 1px solid #041930 !important;
    color: #041930 !important;
    background-color: transparent !important;
  }
  .btn-deepblue:hover {
    background-color: #041930 !important;
    color: white !important;
  }
  .btn-red {
    border: 1px solid #dc2626 !important;
    color: #dc2626 !important;
    background-color: transparent !important;
  }
  .btn-red:hover {
    background-color: #dc2626 !important;
    color: white !important;
  }
  .btn-gold i,
  .btn-deepblue i,
  .btn-red i {
    color: inherit !important;
  }
</style>
