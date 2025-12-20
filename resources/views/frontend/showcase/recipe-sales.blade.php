@extends('frontend.layouts.app')

@section('title', 'Ventas de recetas por fecha')

@section('content')
<div class="container py-5">

  {{-- Sección del formulario de filtro --}}
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-center">
        {{-- Filtro producto --}}
        <div class="col-md-3">
          <label class="form-label">Producto</label>
          <select name="recipe_id" class="form-select">
            <option value="">Todos los productos</option>
            @foreach($recipes as $id => $name)
              <option value="{{ $id }}" @selected($id == $recipeId)>{{ $name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Filtro categoría --}}
        <div class="col-md-3">
          <label class="form-label">Categoría</label>
          <select name="category_id" class="form-select">
            <option value="">Todas las categorías</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" @selected($cat->id == $categoryId)>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Filtro departamento --}}
        <div class="col-md-3">
          <label class="form-label">Departamento</label>
          <select name="department_id" class="form-select">
            <option value="">Todos los departamentos</option>
            @foreach($departments as $dept)
              <option value="{{ $dept->id }}" @selected($dept->id == $departmentId)>{{ $dept->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Rango de fechas --}}
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
        </div>

        {{-- Botón aplicar filtros --}}
        <div class="col-md-2 text-end">
          <button class="btn btn-primary w-100">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de resultados --}}
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table  data-page-length="25"class="table table-striped table-bordered mb-0">
        <thead class="table-light">
          <tr>
            <th>Producto</th>
            <th>Categoría</th>
            <th>Departamento</th>
            <th class="text-end">Piezas vendidas</th>
            <th class="text-end">Desperdicio</th>
            <th class="text-end">Ingresos totales (€)</th>
          </tr>
        </thead>
        <tbody>
          @php
            $grandSold = $grandWaste = $grandRevenue = 0;
          @endphp

          @forelse($recordsByRecipe as $rId => $days)
            @php
              $rec     = $recipes->contains($rId) ? $recipes->get($rId) : '–';
              $model   = $days->first()->first()->recipe;
              $cat     = $model->category?->name ?? '–';
              $dept    = $model->department?->name ?? '–';
              $sold    = $days->flatten()->sum('sold');
              $waste   = $days->flatten()->sum('waste');
              $revenue = $days->flatten()->sum('actual_revenue');

              $grandSold    += $sold;
              $grandWaste   += $waste;
              $grandRevenue += $revenue;
            @endphp

            {{-- Fila padre --}}
            <tr class="accordion-toggle" 
                data-bs-toggle="collapse"
                data-bs-target="#details-{{ $rId }}"
                aria-expanded="false"
                style="cursor: pointer"
            >
              <td>
                <i class="bi bi-caret-down-fill me-1 toggle-icon" id="icon-{{ $rId }}"></i>
                {{ $model->recipe_name }}
              </td>
              <td>{{ $cat }}</td>
              <td>{{ $dept }}</td>
              <td class="text-end">{{ $sold }}</td>
              <td class="text-end">{{ $waste }}</td>
              <td class="text-end">{{ number_format($revenue, 2) }}</td>
            </tr>

            {{-- Fila detallada --}}
            <tr class="collapse" id="details-{{ $rId }}">
              <td colspan="6" class="p-0">
                <table  data-page-length="25"class="table table-sm mb-0">
                  <thead>
                    <tr class="table-light">
                      <th>Fecha</th>
                      <th class="text-end">Vendido</th>
                      <th class="text-end">Desperdicio</th>
                      <th class="text-end">Ingresos (€)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($days as $date => $rowsOnDate)
                      <tr>
                        <td>{{ $date }}</td>
                        <td class="text-end">{{ $rowsOnDate->sum('sold') }}</td>
                        <td class="text-end">{{ $rowsOnDate->sum('waste') }}</td>
                        <td class="text-end">{{ number_format($rowsOnDate->sum('actual_revenue'), 2) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </td>
            </tr>

          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-3">
                No hay datos para esa selección.
              </td>
            </tr>
          @endforelse
        </tbody>

        @if($recordsByRecipe->isNotEmpty())
          <tfoot class="table-light">
            <tr>
              <th colspan="3">Total</th>
              <th class="text-end">{{ $grandSold }}</th>
              <th class="text-end">{{ $grandWaste }}</th>
              <th class="text-end">{{ number_format($grandRevenue, 2) }}</th>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>

</div>
@endsection

{{-- Incluir el paquete JS de Bootstrap --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Obtener todos los elementos colapsables (las filas de la tabla)
    const collapsibleElements = document.querySelectorAll('.accordion-toggle');
    
    collapsibleElements.forEach(item => {
      item.addEventListener('click', function () {
        // Obtener el ID de destino y el icono
        const targetId = item.getAttribute('data-bs-target').substring(1); // Quitar el carácter '#'
        const icon = document.getElementById('icon-' + targetId);

        // Alternar el colapso
        const collapseElement = document.getElementById(targetId);

        // Comprobar si el colapso está abierto o cerrado y alternar el icono en consecuencia
        if (collapseElement.classList.contains('show')) {
          icon.classList.remove('bi-caret-up-fill');
          icon.classList.add('bi-caret-down-fill');
        } else {
          icon.classList.remove('bi-caret-down-fill');
          icon.classList.add('bi-caret-up-fill');
        }
      });
    });
  });
</script>
@endpush
