{{-- resources/views/frontend/recipe-categories/index.blade.php --}}
@extends('frontend.layouts.app')

@section('title','Gestión Categorías Recetas')

@section('content')



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
    color: red !important;
    background-color: transparent !important;
  }
  .btn-red:hover {
    background-color: #ff0000 !important;
    color: white !important;
  }

  table th {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    text-align: center !important;
    vertical-align: middle !important;
    font-weight: bold;
    cursor: pointer;
  }

  table td {
    text-align: center !important;
    vertical-align: middle !important;
  }

  /* Color de las flechas de ordenación */
  table.dataTable thead .sorting:after,
  table.dataTable thead .sorting_asc:after,
  table.dataTable thead .sorting_desc:after {
    color: #041930 !important;
  }
</style>

<div class="container py-5 px-md-5">
  <!-- Tarjeta de formulario -->
  <div class="card border-primary shadow-sm mb-5">
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-tags fs-4 me-2" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        {{ isset($category) ? 'Editar Categoría Receta' : 'Añadir Categoría Receta' }}
      </h5>
    </div>
    <div class="card-body">
      <form
        action="{{ isset($category) ? route('recipe-categories.update', $category->id) : route('recipe-categories.store') }}"
        method="POST"
        class="row g-3 needs-validation"
        novalidate>
        @csrf
        @if(isset($category)) @method('PUT') @endif

        <div class="col-md-8">
          <label for="categoryName" class="form-label fw-semibold">Nombre categoría</label>
          <input
            type="text"
            id="categoryName"
            name="name"
            class="form-control form-control-lg"
            placeholder="ej. Postre"
            value="{{ old('name', $category->name ?? '') }}"
            required>
          <div class="invalid-feedback">Introduce un nombre para la categoría.</div>
        </div>

        <div class="col-12 text-end">
          <button type="submit" class="btn btn-lg" style="background-color: #e2ae76; color: #041930;">
            <i class="bi bi-save2 me-2" style="color: #041930;"></i>
            {{ isset($category) ? 'Actualizar categoría' : 'Guardar categoría' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tarjeta de tabla -->
  <div class="card border-primary shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #041930;">
      <h5 class="mb-0 fw-bold d-flex align-items-center" style="color: #e2ae76;">
        <i class="bi bi-tags fs-4 me-2" style="color: #e2ae76;"></i>
        Listado categorías de recetas
      </h5>
    </div>

    <div class="card-body px-4">
      <div class="table-responsive p-3">
        <table  data-page-length="25"id="categoryTable"
               class="table table-bordered table-striped table-hover align-middle mb-0 text-center"
               data-page-length="25">
          <thead style="background-color: #e2ae76; color: #041930;">
            <tr>
              <th>Nombre</th>
              <th>Última actualización</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($categories as $cat)
              <tr>
                <td>{{ $cat->name }}</td>
                <td>{{ $cat->updated_at->format('Y-m-d H:i') }}</td>
                <td>
                  <a href="{{ route('recipe-categories.edit', $cat) }}" class="btn btn-sm btn-gold me-1" title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="{{ route('recipe-categories.show', $cat) }}" class="btn btn-sm btn-deepblue me-1" title="Ver">
                    <i class="bi bi-eye"></i>
                  </a>
                  <form action="{{ route('recipe-categories.destroy', $cat) }}"
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
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (window.$ && $.fn.DataTable) {
      // Inicializar DataTable (orden de una sola columna)
      var table = $('#categoryTable').DataTable({
        paging:     true,
        ordering:   true,
        responsive: true,
        pageLength: $('#categoryTable').data('page-length') || 25,
        order:      [[0, 'asc']],
        orderMulti: false, // evitar multi-columna
        language: {
          lengthMenu:    "Mostrar _MENU_ elementos por página",
          search:        "Buscar:",
          info:          "Mostrando de _START_ a _END_ de _TOTAL_ elementos",
          zeroRecords:   "No se encontraron registros",
          paginate: {
            first:    "<<",
            previous: "<",
            next:     ">",
            last:     ">>"
          }
        },
        columnDefs: [
          { targets: 2, orderable: false } // Acciones no ordenable
        ]
      });

      // Restaurar orden previo (session)
      try {
        var savedCol = sessionStorage.getItem('cat_sort_col');
        var savedDir = sessionStorage.getItem('cat_sort_dir');
        if (savedCol !== null && savedDir) {
          table.order([parseInt(savedCol,10), savedDir]).draw();
        }
      } catch(e){}

      // Alternancia de 2 estados (asc <-> desc únicamente)
      $('#categoryTable thead').on('click', 'th', function(e) {
        var colIdx = $(this).index();
        // Ignorar no ordenable (Acciones)
        var colSettings = table.settings()[0].aoColumns[colIdx];
        if (colSettings.bSortable === false) return;

        var current = table.order();
        var currentCol = current.length ? current[0][0] : null;
        var currentDir = current.length ? current[0][1] : 'asc';

        if (currentCol === colIdx) {
          var newDir = currentDir === 'asc' ? 'desc' : 'asc';
          table.order([colIdx, newDir]).draw();
        } else {
          table.order([colIdx, 'asc']).draw();
        }

        // Persistir
        try {
          var ord = table.order();
          sessionStorage.setItem('cat_sort_col', ord[0][0]);
          sessionStorage.setItem('cat_sort_dir', ord[0][1]);
        } catch(e){}
      });

      // Evitar multi-orden con shift
      $('#categoryTable thead').on('mousedown', 'th', function(e){
        if (e.shiftKey) e.preventDefault();
      });
    }

    // Validación de formulario
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
@endsection
