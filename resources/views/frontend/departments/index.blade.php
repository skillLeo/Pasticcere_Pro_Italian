{{-- resources/views/frontend/departments/index.blade.php --}}
@extends('frontend.layouts.app')

@section('title', 'Todos los Departamentos')

@section('content')
<div class="container py-5 px-md-5">

  <!-- Agregar / Editar Departamento -->
  <div class="card mb-5 border-primary shadow-sm">
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-building fs-4 me-2" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        {{ isset($department) ? 'Editar Departamento' : 'Agregar Departamento' }}
      </h5>
    </div>
    <div class="card-body">
      <form
        action="{{ isset($department) ? route('departments.update', $department) : route('departments.store') }}"
        method="POST"
        class="needs-validation row g-3"
        novalidate>
        @csrf
        @if(isset($department)) @method('PUT') @endif

        <div class="col-md-8">
          <label for="name" class="form-label fw-semibold">Nombre del Departamento</label>
          <input
            type="text"
            name="name"
            id="name"
            class="form-control form-control-lg"
            placeholder="p. ej. Pastelería, Pizzería, Chocolatería"
            value="{{ old('name', $department->name ?? '') }}"
            required>
          <div class="invalid-feedback">Por favor, introduce el nombre del departamento.</div>
        </div>

        <div class="col-12 text-end">
          <button type="submit" class="btn btn-gold-filled btn-lg">
            <i class="bi bi-save2 me-2"></i>
            {{ isset($department) ? 'Actualizar Departamento' : 'Guardar Departamento' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla Departamentos -->
  <div class="card border-primary shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #041930;">
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        <i class="bi bi-building me-2"></i> Todos los Departamentos
      </h5>
      <a href="{{ route('departments.create') }}" class="btn btn-gold btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Nuevo Departamento
      </a>
    </div>
    <div class="card-body table-responsive">
      <table
        id="departmentsTable"
        class="table table-bordered table-striped table-hover align-middle text-center mb-0"
        data-page-length="25">
        <thead>
          <tr>
            <th class="sortable">Nombre del Departamento <span class="sort-indicator"></span></th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($departments as $department)
            <tr>
              <td>{{ $department->name ?? '—' }}</td>
              <td>
                <a href="{{ route('departments.show', $department) }}" class="btn btn-sm btn-deepblue me-1" title="Ver">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="{{ route('departments.edit', $department) }}" class="btn btn-sm btn-gold me-1" title="Editar">
                  <i class="bi bi-pencil-square"></i>
                </a>
                <form
                  action="{{ route('departments.destroy', $department) }}"
                  method="POST"
                  class="d-inline"
                  onsubmit="return confirm('¿Eliminar este departamento?');">
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
              <td colspan="2" class="text-center text-muted">No se encontraron departamentos.</td>
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
    background: transparent !important;
    transition: all 0.2s;
  }
  .btn-gold:hover { background: #e2ae76 !important; color: #fff !important; }

  .btn-deepblue {
    border: 1px solid #041930 !important;
    color: #041930 !important;
    background: transparent !important;
    transition: all 0.2s;
  }
  .btn-deepblue:hover { background: #041930 !important; color: #fff !important; }

  .btn-red {
    border: 1px solid #ff0000 !important;
    color: red !important;
    background: transparent !important;
    transition: all 0.2s;
  }
  .btn-red:hover { background: #ff0000 !important; color: #fff !important; }

  .btn-gold-filled {
    background: #e2ae76 !important;
    color: #041930 !important;
    border: none !important;
    font-weight: 500;
    padding: 10px 24px;
    border-radius: 12px;
    transition: background 0.2s;
  }
  .btn-gold-filled:hover { background: #d89d5c !important; color: #fff !important; }

  table thead th {
    background: #e2ae76 !important;
    color: #041930 !important;
    text-align: center;
    vertical-align: middle;
  }
  table td { vertical-align: middle !important; }

  /* Indicadores de orden personalizado de 2 estados */
  #departmentsTable thead th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    white-space: nowrap;
  }
  #departmentsTable thead th .sort-indicator {
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
  #departmentsTable thead th[data-sort-dir] .sort-indicator { opacity: 1; }

  /* Eliminar las flechas por defecto de DataTables */
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
  document.addEventListener('DOMContentLoaded', function() {
    if (window.$ && $.fn.DataTable) {
      $.fn.dataTable.ext.errMode = 'none';

      const STORE_KEY = 'departments_sort_state';

      const table = $('#departmentsTable').DataTable({
        paging:     true,
        ordering:   true,
        orderMulti: false,
        responsive: true,
        pageLength: $('#departmentsTable').data('page-length') || 10,
        order: [[0,'asc']],
        columnDefs: [
          { orderable: false, targets: -1 } // Acciones
        ],
        language: {
          lengthMenu:    "Mostrar _MENU_ elementos por página",
          zeroRecords:   "No se encontraron registros",
          info:          "Mostrando de _START_ a _END_ de _TOTAL_ elementos",
          infoEmpty:     "Mostrando 0 a 0 de 0 elementos",
          infoFiltered:  "(filtrado de _MAX_ elementos en total)",
          search:        "Buscar:",
          paginate: {
            first:    "Primero",
            previous: "←",
            next:     "→",
            last:     "Último"
          }
        }
      });

      // Restaurar orden previo
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
        $('#departmentsTable thead th.sortable')
          .removeAttr('data-sort-dir')
          .find('.sort-indicator').text('');
        const ord = table.order();
        if (!ord.length) return;
        const col = ord[0][0];
        const dir = ord[0][1];
        const th = $('#departmentsTable thead th').eq(col);
        if (!th.hasClass('sortable')) return;
        th.attr('data-sort-dir', dir);
        th.find('.sort-indicator').text(dir === 'asc' ? '▲' : '▼');
      }
      updateIndicators();

      // Toggle de 2 estados
      $('#departmentsTable thead').on('click', 'th.sortable', function() {
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
          sessionStorage.setItem(STORE_KEY, JSON.stringify({ col: ord[0][0], dir: ord[0][1] }));
        } catch(e){}
      });

      // Evitar multi-orden con Shift
      $('#departmentsTable thead').on('mousedown', 'th', function(e){
        if (e.shiftKey) e.preventDefault();
      });
    }

    // Validación del lado del cliente con Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        this.classList.add('was-validated');
      }, false);
    });
  });
</script>
@endsection
