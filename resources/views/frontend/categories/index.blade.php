{{-- resources/views/frontend/cost_categories/index.blade.php --}}
@extends('frontend.layouts.app')

@section('title', 'Todas las categorías de costo')

@section('content')
<div class="container py-5 px-md-5">

  <!-- Agregar / Modificar categoría -->
  <div class="card border-primary shadow-sm mb-5">
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-list fs-4 me-2" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        {{ isset($category) ? 'Modificar categoría' : 'Añadir categoría' }}
      </h5>
    </div>
    <div class="card-body">
      <form
        action="{{ isset($category) ? route('cost_categories.update', $category) : route('cost_categories.store') }}"
        method="POST"
        class="needs-validation row g-3"
        novalidate>
        @csrf
        @if(isset($category)) @method('PUT') @endif

        <div class="col-md-8">
          <label for="name" class="form-label fw-semibold">Nombre de la categoría</label>
          <input
            type="text"
            id="name"
            name="name"
            class="form-control form-control-lg"
            placeholder="p. ej. Servicios, Alquiler, Embalaje"
            value="{{ old('name', $category->name ?? '') }}"
            required>
          <div class="invalid-feedback">Por favor ingresa un nombre de categoría.</div>
        </div>

        <div class="col-12 text-end">
          <button type="submit" class="btn btn-gold-filled btn-lg">
            <i class="bi bi-save2 me-2"></i>
            {{ isset($category) ? 'Actualizar categoría' : 'Guardar categoría' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla de categorías -->
  <div class="card border-primary shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #041930;">
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        <i class="bi bi-list fs-4 me-2"></i> Categorías de costo
      </h5>
    </div>
    <div class="card-body table-responsive">
      <table
        id="categoriesTable"
        class="table table-bordered table-striped table-hover align-middle text-center mb-0"
           data-page-length="25">
        <thead>
          <tr>
            <th class="sortable">Nombre de la categoría <span class="sort-indicator"></span></th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categories as $cat)
            <tr>
              <td>{{ $cat->name }}</td>
              <td>
                <a href="{{ route('cost_categories.show', $cat) }}" class="btn btn-sm btn-deepblue me-1" title="Ver">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="{{ route('cost_categories.edit', $cat) }}" class="btn btn-sm btn-gold me-1" title="Modificar">
                  <i class="bi bi-pencil-square"></i>
                </a>
                <form action="{{ route('cost_categories.destroy', $cat) }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('¿Eliminar esta categoría?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-red" title="Eliminar">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="2" class="text-muted">No se encontraron categorías.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>


@endsection

<style>
  .btn-gold {
    border: 1px solid #e2ae76 !important;
    color: #e2ae76 !important;
    background-color: transparent !important;
    transition: all 0.2s ease-in-out;
  }
  .btn-gold:hover {
    background-color: #e2ae76 !important;
    color: white !important;
  }

  .btn-deepblue {
    border: 1px solid #041930 !important;
    color: #041930 !important;
    background-color: transparent !important;
    transition: all 0.2s ease-in-out;
  }
  .btn-deepblue:hover {
    background-color: #041930 !important;
    color: white !important;
  }

  .btn-red {
    border: 1px solid #ff0000 !important;
    color: red !important;
    background-color: transparent !important;
    transition: all 0.2s ease-in-out;
  }
  .btn-red:hover {
    background-color: #ff0000 !important;
    color: white !important;
  }

  .btn-gold-filled {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    border: none !important;
    font-weight: 500;
    padding: 10px 24px;
    border-radius: 12px;
    transition: background-color 0.2s ease;
  }
  .btn-gold-filled:hover {
    background-color: #d89d5c !important;
    color: white !important;
  }

  table th {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    text-align: center;
    vertical-align: middle;
  }
  table td {
    text-align: center;
    vertical-align: middle;
  }

  /* --- Indicadores de ordenamiento personalizados de 2 estados --- */
  #categoriesTable thead th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    white-space: nowrap;
  }
  #categoriesTable thead th .sort-indicator {
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
  #categoriesTable thead th[data-sort-dir] .sort-indicator { opacity: 1; }

  /* Eliminar flechas predeterminadas de DataTables */
  table.dataTable thead .sorting:after,
  table.dataTable thead .sorting_asc:after,
  table.dataTable thead .sorting_desc:after,
  table.dataTable thead .sorting:before,
  table.dataTable thead .sorting_asc:before,
  table.dataTable thead .sorting_desc:before {
    content: '' !important;
  }
</style>

@section('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.$ && $.fn.DataTable) {
      $.fn.dataTable.ext.errMode = 'none';

      const STORE_KEY = 'cost_categories_sort_state';

      const table = $('#categoriesTable').DataTable({
        paging:      true,
        ordering:    true,
        orderMulti:  false, // orden de una sola columna
        responsive:  true,
        pageLength:  $('#categoriesTable').data('page-length') || 10,
        order:       [[0, 'asc']],
        columnDefs: [
          { orderable: false, targets: -1 }
        ],
        language: {
          lengthMenu:    "Mostrar _MENU_ elementos por página",
          zeroRecords:   "Ningún registro encontrado",
          info:          "Mostrando _START_ a _END_ de _TOTAL_ elementos",
          infoEmpty:     "Mostrando 0 a 0 de 0 elementos",
          infoFiltered:  "(filtrados de un total de _MAX_)",
          search:        "Buscar:",
          paginate: {
            first:    "Primero",
            previous: "←",
            next:     "→",
            last:     "Último"
          }
        }
      });

      // Restaurar orden guardado
      try {
        const saved = sessionStorage.getItem(STORE_KEY);
        if (saved) {
          const { col, dir } = JSON.parse(saved);
          if (typeof col === 'number' && (dir === 'asc' || dir === 'desc')) {
            table.order([col, dir]).draw();
          }
        }
      } catch(e){}

      function updateIndicators() {
        $('#categoriesTable thead th.sortable')
          .removeAttr('data-sort-dir')
          .find('.sort-indicator').text('');
        const ord = table.order();
        if (!ord.length) return;
        const col = ord[0][0];
        const dir = ord[0][1];
        const th  = $('#categoriesTable thead th').eq(col);
        if (!th.hasClass('sortable')) return;
        th.attr('data-sort-dir', dir);
        th.find('.sort-indicator').text(dir === 'asc' ? '▲' : '▼');
      }
      updateIndicators();

      // Clic de 2 estados
      $('#categoriesTable thead').on('click', 'th.sortable', function() {
        const idx = $(this).index();
        const colSettings = table.settings()[0].aoColumns[idx];
        if (colSettings.bSortable === false) return;

        const current   = table.order();
        const currentCol= current.length ? current[0][0] : null;
        const currentDir= current.length ? current[0][1] : 'asc';
        const newDir    = (currentCol === idx && currentDir === 'asc') ? 'desc' : 'asc';

        table.order([idx, newDir]).draw();
        updateIndicators();

        try {
          const ord = table.order();
          sessionStorage.setItem(STORE_KEY, JSON.stringify({ col: ord[0][0], dir: ord[0][1] }));
        } catch(e){}
      });

      // Evitar multi-orden con Shift
      $('#categoriesTable thead').on('mousedown', 'th', function(e){
        if (e.shiftKey) e.preventDefault();
      });
    }

    // Validación del lado del cliente
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', e => {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  });
</script>




{{-- 
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.$ && $.fn.DataTable) {
      $.fn.dataTable.ext.errMode = 'none';

      const STORE_KEY = 'cost_categories_sort_state';
      const DEFAULT_LEN = 25;

      const table = $('#categoriesTable').DataTable({
        paging:      true,
        ordering:    true,
        orderMulti:  false, // orden de una sola columna
        responsive:  true,

        // 🔒 Forzar 25 por página en esta tabla (ignorar atributo data)
        pageLength:  DEFAULT_LEN,
        lengthMenu:  [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],

        order:       [[0, 'asc']],
        columnDefs: [
          { orderable: false, targets: -1 }
        ],
        language: {
          lengthMenu:    "Mostrar _MENU_ elementos por página",
          zeroRecords:   "Ningún registro encontrado",
          info:          "Mostrando _START_ a _END_ de _TOTAL_ elementos",
          infoEmpty:     "Mostrando 0 a 0 de 0 elementos",
          infoFiltered:  "(filtrados de un total de _MAX_)",
          search:        "Buscar:"
          // ❗️No establecer paginación aquí para que tu global << < > >> se mantenga
        }
      });

      // Seguridad: si algo aún lo establece en un número diferente, forzar 25 y redibujar
      if (table.page.len() !== DEFAULT_LEN) {
        table.page.len(DEFAULT_LEN).draw(false);
      }

      // Restaurar orden guardado (sesión)
      try {
        const saved = sessionStorage.getItem(STORE_KEY);
        if (saved) {
          const { col, dir } = JSON.parse(saved);
          if (typeof col === 'number' && (dir === 'asc' || dir === 'desc')) {
            table.order([col, dir]).draw();
          }
        }
      } catch(e){}

      function updateIndicators() {
        $('#categoriesTable thead th.sortable')
          .removeAttr('data-sort-dir')
          .find('.sort-indicator').text('');
        const ord = table.order();
        if (!ord.length) return;
        const col = ord[0][0];
        const dir = ord[0][1];
        const th  = $('#categoriesTable thead th').eq(col);
        if (!th.hasClass('sortable')) return;
        th.attr('data-sort-dir', dir);
        th.find('.sort-indicator').text(dir === 'asc' ? '▲' : '▼');
      }
      updateIndicators();

      // Clic de 2 estados
      $('#categoriesTable thead').on('click', 'th.sortable', function() {
        const idx = $(this).index();
        const colSettings = table.settings()[0].aoColumns[idx];
        if (colSettings.bSortable === false) return;

        const current    = table.order();
        const currentCol = current.length ? current[0][0] : null;
        const currentDir = current.length ? current[0][1] : 'asc';
        const newDir     = (currentCol === idx && currentDir === 'asc') ? 'desc' : 'asc';

        table.order([idx, newDir]).draw();
        updateIndicators();

        try {
          const ord = table.order();
          sessionStorage.setItem(STORE_KEY, JSON.stringify({ col: ord[0][0], dir: ord[0][1] }));
        } catch(e){}
      });

      // Evitar multi-orden con Shift
      $('#categoriesTable thead').on('mousedown', 'th', function(e){
        if (e.shiftKey) e.preventDefault();
      });
    }

    // Validación del lado del cliente
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', e => {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  });
</script> --}}
@endsection
