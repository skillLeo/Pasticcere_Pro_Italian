{{-- resources/views/frontend/showcase/show.blade.php --}}
@extends('frontend.layouts.app')

@section('title', $showcase->showcase_date->format('Y-m-d'))

@section('content')
<div class="container py-5">
  <div class="card border-primary shadow-lg rounded-3 overflow-hidden">
    <!-- Encabezado con icono y fecha -->
    <div class="card-header d-flex align-items-center"
         style="background-color: #041930; color: #e2ae76;">
      <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg"
           viewBox="0 0 512 512"
           style="width: 30px; height: 30px; margin-right: 8px; fill: #e2ae76;">
        <!-- (Rutas SVG omitidas por brevedad) -->
      </svg>
      <h4 class="mb-0" style="font-size: 16px; color: #e2ae76;">
        Escaparate: {{ $showcase->showcase_date->format('Y-m-d') }}
      </h4>
    </div>

    <div class="card-body">
      <!-- Cuadrícula de detalles del escaparate -->
      <div class="row row-cols-1 row-cols-md-2 g-4 mb-3" style="width: 50%">
        <div class="col">
          <p class="text-uppercase text-muted small mb-1" style="font-size: 14px;">
            Punto de equilibrio (€)
          </p>
          <p class="fs-5 fw-bold mb-0" style="font-size: 16px;">
            €{{ number_format($showcase->break_even, 2) }}
          </p>
        </div>
        <div class="col">
          <p class="text-uppercase text-muted small mb-1" style="font-size: 14px;">
            Ingresos totales (€)
          </p>
          <p class="fs-5 fw-bold mb-0" style="font-size: 16px;">
            €{{ number_format($showcase->total_revenue, 2) }}
          </p>
        </div>
        <div class="col">
          <p class="text-uppercase text-muted small mb-1" style="font-size: 14px;">
            Extra (€)
          </p>
          <p class="fs-5 fw-bold mb-0" style="font-size: 16px;">
            €{{ number_format($showcase->plus, 2) }}
          </p>
        </div>
        <div class="col">
          <p class="text-uppercase text-muted small mb-1" style="font-size: 14px;">
            Margen real (€)
          </p>
          <p class="fs-5 fw-bold mb-0" style="font-size: 16px;">
            @if($showcase->real_margin >= 0)
              <span class="text-success">€{{ number_format($showcase->real_margin,2) }}</span>
            @else
              <span class="text-danger">€{{ number_format($showcase->real_margin,2) }}</span>
            @endif
          </p>
        </div>
        <div class="col">
          <p class="text-uppercase text-muted small mb-1" style="font-size: 14px;">
            Última actualización
          </p>
          <p class="fs-5 mb-0" style="font-size: 16px;">
            {{ optional($showcase->updated_at)->format('Y-m-d H:i') ?? '—' }}
          </p>
        </div>
      </div>

      <hr class="border-secondary">

      <!-- Tabla de desglose de productos del escaparate -->
      <h5 class="mt-4" style="font-size: 16px;">Detalle de productos del escaparate</h5>
      <div class="table-responsive">
        <table  data-page-length="25"class="table table-striped table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th style="font-size: 14px;">Receta</th>
              <th style="font-size: 14px;">Departamento</th>
              <th style="font-size: 14px;">Precio</th>
              <th style="font-size: 14px;">Cantidad</th>
              <th style="font-size: 14px;">Vendidos</th>
              <th style="font-size: 14px;">Reutilización</th>
              <th style="font-size: 14px;">Desperdicios</th>
              <th style="font-size: 14px;">Ingresos potenciales</th>
              <th style="font-size: 14px;">Ingresos efectivos</th>
            </tr>
          </thead>
          <tbody>
            @php
              $totals = [
                'quantity'=>0,
                'sold'=>0,
                'reuse'=>0,
                'waste'=>0,
                'potential'=>0,
                'actual'=>0
              ];
            @endphp

            @foreach($showcase->recipes as $item)
              <tr>
                <td style="font-size: 14px;">
                  {{ $item->recipe->recipe_name }}
                </td>
                <td style="font-size: 14px;">
                  {{ optional($item->recipe->department)->name ?? 'N/A' }}
                </td>
                <td style="font-size: 14px;">
                  €{{ number_format($item->price, 2) }}
                </td>
                <td style="font-size: 14px;">
                  {{ $item->quantity }}
                </td>
                <td style="font-size: 14px;">
                  {{ $item->sold }}
                </td>
                <td style="font-size: 14px;">
                  {{ $item->reuse }}
                </td>
                <td style="font-size: 14px;">
                  {{ $item->waste }}
                </td>
                <td style="font-size: 14px;">
                  €{{ number_format($item->potential_income, 2) }}
                </td>
                <td style="font-size: 14px;">
                  €{{ number_format($item->actual_revenue, 2) }}
                </td>
              </tr>
              @php
                $totals['quantity'] += $item->quantity;
                $totals['sold']     += $item->sold;
                $totals['reuse']    += $item->reuse;
                $totals['waste']    += $item->waste;
                $totals['potential']+= $item->potential_income;
                $totals['actual']   += $item->actual_revenue;
              @endphp
            @endforeach
          </tbody>
          <tfoot>
            <tr class="table-warning">
              <td colspan="3" class="text-end" style="font-size: 14px;">
                <strong>Total:</strong>
              </td>
              <td style="font-size: 14px;">{{ $totals['quantity'] }}</td>
              <td style="font-size: 14px;">{{ $totals['sold'] }}</td>
              <td style="font-size: 14px;">{{ $totals['reuse'] }}</td>
              <td style="font-size: 14px;">{{ $totals['waste'] }}</td>
              <td style="font-size: 14px;">
                €{{ number_format($totals['potential'], 2) }}
              </td>
              <td style="font-size: 14px;">
                €{{ number_format($totals['actual'], 2) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Acciones -->
      <div class="mt-4 text-end">
        <a href="{{ route('showcase.edit', $showcase) }}" class="btn btn-gold me-2">
          <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('showcase.index') }}" class="btn btn-deepblue me-2">
          <i class="bi bi-arrow-left me-1"></i> Volver a la lista
        </a>
        <form action="{{ route('showcase.destroy', $showcase) }}"
              method="POST" class="d-inline"
              onsubmit="return confirm('¿Eliminar este escaparate?');">
          @csrf
          @method('DELETE')
          <button class="btn btn-red" type="submit">
            <i class="bi bi-trash me-1"></i> Eliminar
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
    border: 1px solid #ff0000 !important;
    color: #ff0000 !important;
    background-color: transparent !important;
  }
  .btn-red:hover {
    background-color: #ff0000 !important;
    color: white !important;
  }
</style>
