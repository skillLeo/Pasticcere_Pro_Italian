@extends('frontend.layouts.app')

@section('title','Escaparate de Pasteleros')

@section('content')
<div class="container py-5 px-md-5">

  <!-- Formulario Añadir/Editar Pastelero -->
  <div class="card mb-4 border-primary shadow-sm">
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-egg-fried fs-4 me-2" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
        {{ isset($pastryChef) ? 'Editar pastelero' : 'Añadir pastelero' }}
      </h5>
    </div>
    <div class="card-body">
      <form 
        action="{{ isset($pastryChef) ? route('pastry-chefs.update', $pastryChef) : route('pastry-chefs.store') }}" 
        method="POST" 
        class="row g-3 needs-validation" 
        novalidate>
        @csrf
        @if(isset($pastryChef)) @method('PUT') @endif

        <div class="col-md-4">
          <label for="Name" class="form-label fw-semibold">Nombre del pastelero</label>
          <input 
            type="text" 
            id="Name" 
            name="name" 
            class="form-control form-control-lg"
            value="{{ old('name', $pastryChef->name ?? '') }}"
            placeholder="Introduce el nombre del pastelero" 
            required>
          <div class="invalid-feedback">Por favor introduce el nombre del chef.</div>
        </div>

        <div class="col-md-4">
          <label for="Email" class="form-label fw-semibold">Correo electrónico del pastelero</label>
          <input 
            type="email" 
            id="Email" 
            name="email" 
            class="form-control form-control-lg"
            value="{{ old('email', $pastryChef->email ?? '') }}"
            placeholder="Introduce el correo electrónico del pastelero">
          <div class="invalid-feedback">Por favor introduce el correo electrónico del chef.</div>
        </div>

        <div class="col-md-4">
          <label for="Phone" class="form-label fw-semibold">Teléfono del pastelero</label>
          <input 
            type="text" 
            id="Phone" 
            name="phone" 
            class="form-control form-control-lg"
            value="{{ old('phone', $pastryChef->phone ?? '') }}"
            placeholder="Introduce el teléfono del pastelero">
          <div class="invalid-feedback">Por favor introduce el teléfono del pastelero.</div>
        </div>

        <div class="col-12 text-end">
          <button type="submit" class="btn btn-gold-filled btn-lg">
            <i class="bi bi-save2 me-2"></i>
            {{ isset($pastryChef) ? 'Actualizar pastelero' : 'Guardar pastelero' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla de Pasteleros -->
  <div class="card border-primary shadow-sm">
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <i class="bi bi-people fs-4 me-2" style="color: #e2ae76;"></i>
      <h5 class="mb-0 fw-bold" style="color: #e2ae76;">Lista de pasteleros</h5>
    </div>
    <div class="card-body table-responsive">
      <table
        id="pastryChefsTable"
        class="table table-bordered table-striped table-hover align-middle text-center mb-0"
        data-page-length="25">
        <thead>
          <tr>
            <th class="sortable">Nombre</th>
            <th class="sortable">Correo electrónico</th>
            <th class="sortable">Teléfono</th>
            <th class="sortable">Última actualización</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pastryChefs as $chef)
            <tr>
              <td>{{ $chef->name }}</td>
              <td>{{ $chef->email ?? '—' }}</td>
              <td>{{ $chef->phone ?? '—' }}</td>
              <td>{{ optional($chef->updated_at)?->format('Y-m-d H:i') ?? '—' }}</td>
              <td>
                <a href="{{ route('pastry-chefs.show', $chef) }}" class="btn btn-sm btn-deepblue me-1" title="Ver">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="{{ route('pastry-chefs.edit', $chef) }}" class="btn btn-sm btn-gold me-1" title="Editar">
                  <i class="bi bi-pencil-square"></i>
                </a>
                <form 
                  action="{{ route('pastry-chefs.destroy', $chef) }}" 
                  method="POST" 
                  class="d-inline"
                  onsubmit="return confirm('¿Eliminar este chef?');">
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
              <td colspan="5" class="text-center text-muted">No se ha encontrado ningún pastelero.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection

<style>
  table thead th {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    text-align: center !important;
    vertical-align: middle !important;
    cursor: pointer;
  }
  table thead th:last-child {
    cursor: default;
  }

  table tbody td {
    text-align: center !important;
    vertical-align: middle !important;
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

  .btn-gold, .btn-deepblue, .btn-red {
    border: 1px solid !important;
    font-weight: 500;
  }

  .btn-gold { border-color: #e2ae76 !important; color: #e2ae76 !important; }
  .btn-gold:hover { background-color: #e2ae76 !important; color: white !important; }

  .btn-deepblue { border-color: #041930 !important; color: #041930 !important; }
  .btn-deepblue:hover { background-color: #041930 !important; color: white !important; }

  .btn-red { border-color: #ff0000 !important; color: red !important; }
  .btn-red:hover { background-color: #ff0000 !important; color: white !important; }

  /* Color de las flechas de ordenación de DataTables (si se usa el plugin) */
  table.dataTable thead .sorting:after,
  table.dataTable thead .sorting_asc:after,
  table.dataTable thead .sorting_desc:after {
    color: #041930 !important;
  }
</style>

@section('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.$ && $.fn.DataTable) {
      $.fn.dataTable.ext.errMode = 'none';

      const table = $('#pastryChefsTable').DataTable({
        paging:      true,
        ordering:    true,
        responsive:  true,
        pageLength:  $('#pastryChefsTable').data('page-length') || 10,
        order:       [[0,'asc']],
        orderMulti:  false,
        columnDefs: [
          { orderable: false, targets: -1 }
        ],
        language: {
          search:        "Buscar:",
          lengthMenu:    "Mostrar _MENU_ elementos",
          info:          "Mostrando de _START_ a _END_ de _TOTAL_ elementos",
          infoEmpty:     "No hay elementos disponibles",
          zeroRecords:   "No se han encontrado coincidencias"
        }
      });

      // Restaurar ordenación previa (sessionStorage)
      try {
        const sc = sessionStorage.getItem('pastry_sort_col');
        const sd = sessionStorage.getItem('pastry_sort_dir');
        if (sc !== null && sd) {
          table.order([parseInt(sc,10), sd]).draw();
        }
      } catch(e){}

      // Toggle de dos estados en el encabezado
      $('#pastryChefsTable thead').on('click', 'th', function() {
        const idx = $(this).index();
        const colSettings = table.settings()[0].aoColumns[idx];
        if (colSettings.bSortable === false) return; // saltar "Acciones"

        const current = table.order();
        const currentCol = current.length ? current[0][0] : null;
        const currentDir = current.length ? current[0][1] : 'asc';

        if (currentCol === idx) {
          const newDir = currentDir === 'asc' ? 'desc' : 'asc';
          table.order([idx, newDir]).draw();
        } else {
          table.order([idx, 'asc']).draw();
        }

        try {
          const ord = table.order();
          sessionStorage.setItem('pastry_sort_col', ord[0][0]);
          sessionStorage.setItem('pastry_sort_dir', ord[0][1]);
        } catch(e){}
      });

      // Evitar ordenación múltiple con Shift
      $('#pastryChefsTable thead').on('mousedown', 'th', function(e){
        if (e.shiftKey) e.preventDefault();
      });
    }

    // Validación Bootstrap
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
