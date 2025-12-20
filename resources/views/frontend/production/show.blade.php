{{-- resources/views/frontend/production/show.blade.php --}}
@extends('frontend.layouts.app')

@section('title', 'Producción: ' . $production->production_date)

@section('content')
<div class="container py-5 px-md-4">

  {{-- Tarjeta --}}
  <div class="card border-primary shadow-lg rounded-3 overflow-hidden">

    {{-- Encabezado --}}
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-gear-fill fs-4 me-3" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        Registro de producción: {{ $production->production_date }}
      </h5>
    </div>

    <div class="card-body">

      {{-- Fila de resumen --}}
      <div class="row mb-4">
        <div class="col-md-4">
          <h6 class="text-uppercase text-muted small">Líneas producidas</h6>
          <p class="fs-4 fw-bold mb-0">{{ $production->details->count() }}</p>
        </div>
        <div class="col-md-4">
          <h6 class="text-uppercase text-muted small">Potencial total (€)</h6>
          <p class="fs-4 fw-bold mb-0">
            €{{ number_format($production->total_potential_revenue, 2) }}
          </p>
        </div>
      </div>

      {{-- Controles de filtro / orden / impresión --}}
      <form method="GET" class="row mb-3 gx-2 gy-2">
        <div class="col-auto">
          <select name="chef_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Todos los chefs</option>
            @foreach($allChefs as $id => $name)
              <option value="{{ $id }}" {{ $id == $selectedChef ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto">
          @php
            $opposite = $sortDir==='asc' ? 'desc' : 'asc';
            $qs = array_merge(request()->all(), ['sort'=>'chef','direction'=>$opposite]);
          @endphp
          <a href="?{{ http_build_query($qs) }}" class="btn btn-sm btn-outline-secondary">
            Ordenar por chef @if($sortDir==='asc') ↑ @else ↓ @endif
          </a>
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
            Imprimir tabla
          </button>
        </div>
      </form>

      {{-- Tabla de detalles --}}
      <div class="table-responsive">
        <table data-page-length="25" class="table table-bordered mb-0 align-middle text-center print-only-table">
          <thead style="background-color: #e2ae76; color: #041930;">
            <tr>
              <th>Receta</th>
              <th>Chef</th>
              <th>Cant.</th>
              <th>Tiempo (m)</th>
              <th>Equipo</th>
              <th>Potencial (€)</th>
            </tr>
          </thead>
          <tbody>
            @php
              $totalQty = 0;
              $totalExecTime = 0;
              $totalPotential = 0;
            @endphp

            @foreach($details as $detail)
              @php
                $ids = is_array($detail->equipment_ids)
                  ? $detail->equipment_ids
                  : (strlen($detail->equipment_ids)
                      ? explode(',', $detail->equipment_ids)
                      : []);
                $names = collect($ids)
                  ->map(fn($i)=>($equipmentMap[trim($i)] ?? null))
                  ->filter()->unique()->values();
                $equip = $names->implode(', ');

                $totalQty += $detail->quantity;
                $totalExecTime += $detail->execution_time;
                $totalPotential += $detail->potential_revenue;
              @endphp
              <tr>
                <td>{{ $detail->recipe->recipe_name }}</td>
                <td>{{ $detail->chef->name }}</td>
                <td>{{ $detail->quantity }}</td>
                <td>{{ $detail->execution_time }}</td>
                <td>{{ $equip }}</td>
                <td>€{{ number_format($detail->potential_revenue, 2) }}</td>
              </tr>
            @endforeach

            <tr class="fw-bold">
              <td colspan="2" class="text-end">Total:</td>
              <td>{{ $totalQty }}</td>
              <td>{{ $totalExecTime }}</td>
              <td></td>
              <td>€{{ number_format($totalPotential, 2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- Acciones --}}
      <div class="mt-4 text-end">
        <a href="{{ route('production.edit', $production) }}" class="btn btn-gold me-2">
          <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('production.index') }}" class="btn btn-deepblue me-2">
          <i class="bi bi-arrow-left me-1"></i> Atrás
        </a>
        <form action="{{ route('production.destroy', $production) }}"
              method="POST" class="d-inline"
              onsubmit="return confirm('¿Eliminar este registro?');">
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

{{-- Botones y estilos de impresión --}}
<style>
  .btn-gold {
    border: 1px solid #e2ae76!important;
    color: #e2ae76!important;
    background: transparent!important;
  }
  .btn-gold:hover {
    background: #e2ae76!important;
    color: #fff!important;
  }
  .btn-deepblue {
    border: 1px solid #041930!important;
    color: #041930!important;
    background: transparent!important;
  }
  .btn-deepblue:hover {
    background: #041930!important;
    color: #fff!important;
  }
  .btn-red {
    border: 1px solid red!important;
    color: red!important;
    background: transparent!important;
  }
  .btn-red:hover {
    background: red!important;
    color: #fff!important;
  }

  @media print {
    body * { visibility: hidden; }
    .print-only-table, .print-only-table * {
      visibility: visible;
    }
    .print-only-table {
      position: absolute;
      top: 0; left: 0; width: 100%;
    }
  }
</style>
@endsection
