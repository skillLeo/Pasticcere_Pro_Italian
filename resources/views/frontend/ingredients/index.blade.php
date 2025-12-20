@extends('frontend.layouts.app')

@section('title', 'Escaparate de Ingredientes')

@section('content')
<div class="container py-5 px-md-5">

  <!-- Formulario Añadir / Editar Ingrediente -->
  <div class="card border-primary shadow-sm mb-5">
    <div class="card-header d-flex align-items-center"
         style="background-color: #041930; padding: .5rem; border-top-left-radius: .5rem; border-top-right-radius: .5rem;">
      <!-- Icono SVG Ingrediente -->
      <svg class="me-2" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"
           style="width: 1.6vw; height: 1.6vw; color: #e2ae76; fill: currentColor;">
        <!-- (your SVG paths here) -->
      </svg>

      <h5 class="mb-0 fw-bold" style="color: #e2ae76; font-size: 1.6vw;">
        {{ isset($ingredient) ? 'Modificar Ingrediente' : 'Añadir Ingrediente' }}
      </h5>
    </div>

    <div class="card-body">
      <form
        action="{{ isset($ingredient) ? route('ingredients.update', $ingredient) : route('ingredients.store') }}"
        method="POST"
        class="row g-3 needs-validation"
        novalidate
      >
        @csrf
        @if (isset($ingredient)) @method('PUT') @endif

        <div class="col-md-6">
          <label for="ingredientName" class="form-label fw-semibold">Nombre del ingrediente</label>
            <input
              type="text"
              id="ingredientName"
              name="ingredient_name"
              class="form-control form-control-lg"
              placeholder="p. ej. Harina 00"
              value="{{ old('ingredient_name', $ingredient->ingredient_name ?? '') }}"
              required
            >
            <div class="invalid-feedback">Introduce un nombre de ingrediente.</div>
        </div>

        <div class="col-md-6">
          <label for="pricePerKg" class="form-label fw-semibold">Precio por kg</label>
          <div class="input-group input-group-lg has-validation">
            <span class="input-group-text">€</span>
            <input
              type="number"
              id="pricePerKg"
              name="price_per_kg"
              class="form-control"
              step="0.01"
              placeholder="0,00"
              value="{{ old('price_per_kg', $ingredient->price_per_kg ?? '') }}"
              required
            >
            <span class="input-group-text">/kg</span>
            <div class="invalid-feedback">Introduce un precio válido.</div>
          </div>
        </div>

        <div class="col-12 text-end">
          <button type="submit"
                  class="btn btn-lg"
                  style="background-color: #e2ae76; color: #041930; border-color: #e2ae76;">
            <i class="bi bi-save2 me-2"></i>
            {{ isset($ingredient) ? 'Actualizar Ingrediente' : 'Guardar Ingrediente' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla de Ingredientes -->
  <div class="card border-primary shadow-sm">
    <div class="card-header" style="background-color: #041930;">
      <h5 class="mb-0" style="color: #e2ae76;">Escaparate de Ingredientes</h5>
    </div>
    <div class="card-body px-4" style="overflow-x: hidden;">
      <div class="table-responsive p-3">
        <table
          id="ingredientsTable"
          class="table table-bordered table-striped table-hover align-middle mb-0 text-center"
          data-page-length="25"
        >
          <thead>
            <tr>
              <th class="sortable">Nombre <span class="sort-indicator"></span></th>
              <th class="sortable">Precio / kg <span class="sort-indicator"></span></th>
              <th class="sortable">Última actualización <span class="sort-indicator"></span></th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($ingredients as $ing)
              <tr>
                <td>{{ $ing->ingredient_name }}</td>
                <td data-order="{{ $ing->price_per_kg }}">€{{ number_format($ing->price_per_kg, 2) }}</td>
                <td data-order="{{ $ing->updated_at->format('Y-m-d H:i') }}">{{ $ing->updated_at->format('Y-m-d H:i') }}</td>
                <td>
                  <a href="{{ route('ingredients.edit', $ing) }}" class="btn btn-sm btn-gold me-1" title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="{{ route('ingredients.show', $ing) }}" class="btn btn-sm btn-deepblue me-1" title="Ver">
                    <i class="bi bi-eye"></i>
                  </a>
                  <form action="{{ route('ingredients.destroy', $ing) }} "
                        method="POST"
                        class="d-inline"
                        onsubmit="return confirm('¿Eliminar este ingrediente?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-red" title="Eliminar">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection

<style>
  /* Cabecera de la tabla */
  table.dataTable thead th {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    text-align: center;
    vertical-align: middle;
  }
  #ingredientsTable thead th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    white-space: nowrap;
  }
  #ingredientsTable thead th .sort-indicator {
    display: inline-block;
    width: 14px;
    text-align: center;
    font-size: .7rem;
    line-height: 1;
    margin-left: 4px;
    color: #041930;
    opacity: 0;
    transition: opacity .15s;
  }
  #ingredientsTable thead th[data-sort-dir] .sort-indicator { opacity: 1; }

  /* Eliminar flechas por defecto de DataTables */
  table.dataTable thead .sorting:after,
  table.dataTable thead .sorting_asc:after,
  table.dataTable thead .sorting_desc:after,
  table.dataTable thead .sorting:before,
  table.dataTable thead .sorting_asc:before,
  table.dataTable thead .sorting_desc:before {
    content: '' !important;
  }

  /* Celdas */
  table.dataTable tbody td {
    text-align: center;
    vertical-align: middle;
  }

  /* Evitar scroll horizontal inferior innecesario */
  .card-body[style*="overflow-x"] .table-responsive {
    overflow-x: visible !important;
  }

  /* Botones */
  .btn-gold {
    border: 1px solid #e2ae76 !important;
    color: #e2ae76 !important;
    background-color: transparent !important;
  }
  .btn-gold:hover {
    background-color: #e2ae76 !important;
    color: #fff !important;
  }
  .btn-deepblue {
    border: 1px solid #041930 !important;
    color: #041930 !important;
    background-color: transparent !important;
  }
  .btn-deepblue:hover {
    background-color: #041930 !important;
    color: #fff !important;
  }
  .btn-red {
    border: 1px solid #ff0000 !important;
    color: red !important;
    background-color: transparent !important;
  }
  .btn-red:hover {
    background-color: red !important;
    color: #fff !important;
  }

  /* Estilo del desplegable de longitud de página */
  .dataTables_length select {
    border: 1px solid #e2ae76;
    color: #041930;
    padding-right: 30px;
    background: #fff url('data:image/svg+xml;utf8,<svg fill="%23e2ae76" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center;
    appearance: none;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button {
    white-space: nowrap;
  }
</style>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

  // Cálculo en tiempo real del precio del ingrediente (se mantiene la lógica original)
  const priceInput = document.getElementById("pricePerKg");
  if (priceInput) {
    priceInput.addEventListener("input", function () {
      const price = parseFloat(priceInput.value) || 0;
      const updatedPrice = price.toFixed(2);
      const priceElements = document.querySelectorAll('td[data-price-update]');
      priceElements.forEach(function (el) {
        el.innerText = '€' + updatedPrice;
      });
    });
  }

  if (window.$ && $.fn.DataTable) {
    $.fn.dataTable.ext.errMode = 'none';

    const STORAGE_KEY_COL = 'ingredients_sort_col';
    const STORAGE_KEY_DIR = 'ingredients_sort_dir';

    const table = $('#ingredientsTable').DataTable({
      responsive: true,
      paging: true,
      ordering: true,
      orderMulti: false,   // una sola columna
      pageLength: $('#ingredientsTable').data('page-length') || 10,
      order: [[0,'asc']],
      columnDefs: [
        { orderable: false, targets: -1 } // Acciones
      ],
      language: {
        emptyTable: "No se encontraron ingredientes.",
        search: "_INPUT_",
        searchPlaceholder: "Buscar ingredientes...",
        lengthMenu: "Mostrar _MENU_ elementos",
        zeroRecords: "Ningún ingrediente coincide",
        info: "Mostrando de _START_ a _END_ de _TOTAL_ elementos",
        infoEmpty: "Ningún ingrediente disponible",
        paginate: {
          first: "",
          last:  "",
          next:  "→",
          previous: "←"
        }
      }
    });

    // Restaurar orden guardado
    try {
      const sc = sessionStorage.getItem(STORAGE_KEY_COL);
      const sd = sessionStorage.getItem(STORAGE_KEY_DIR);
      if (sc !== null && sd) {
        table.order([parseInt(sc,10), sd]).draw();
      }
    } catch(e){}

    function updateIndicators() {
      $('#ingredientsTable thead th.sortable').removeAttr('data-sort-dir')
        .find('.sort-indicator').text('');
      const ord = table.order();
      if (!ord.length) return;
      const col = ord[0][0];
      const dir = ord[0][1];
      const th  = $('#ingredientsTable thead th').eq(col);
      if (!th.hasClass('sortable')) return;
      th.attr('data-sort-dir', dir);
      th.find('.sort-indicator').text(dir === 'asc' ? '▲' : '▼');
    }

    updateIndicators();

    // Toggle de 2 estados (asc <-> desc)
    $('#ingredientsTable thead').on('click', 'th.sortable', function() {
      const idx = $(this).index();
      const colSettings = table.settings()[0].aoColumns[idx];
      if (colSettings.bSortable === false) return;

      const current = table.order();
      const currentCol = current.length ? current[0][0] : null;
      const currentDir = current.length ? current[0][1] : 'asc';
      const newDir = (currentCol === idx && currentDir === 'asc') ? 'desc' : 'asc';

      table.order([idx, newDir]).draw();
      updateIndicators();

      try {
        const ord = table.order();
        sessionStorage.setItem(STORAGE_KEY_COL, ord[0][0]);
        sessionStorage.setItem(STORAGE_KEY_DIR, ord[0][1]);
      } catch(e){}
    });

    // Evitar orden múltiple con Shift
    $('#ingredientsTable thead').on('mousedown', 'th', function(e){
      if (e.shiftKey) e.preventDefault();
    });
  }

  // Tooltips de Bootstrap (por si se usan en el futuro)
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

  // Validación de formularios Bootstrap
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
});
</script>
@endsection
