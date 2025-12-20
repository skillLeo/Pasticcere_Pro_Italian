{{-- resources/views/frontend/user-management/users/index.blade.php --}}
@extends('frontend.layouts.app')

@section('title','Usuarios')

@section('content')
@php
    use Carbon\Carbon;
@endphp

<div class="container py-5 px-md-4">

  {{-- Tarjeta del perfil del usuario conectado --}}
  <div class="row mb-5 justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-lg rounded-3 border-0 overflow-hidden">
        <div class="card-body text-center pt-5">
          <h4 class="fw-bold mb-1">{{ auth()->user()->name }}</h4>
          <p class="text-muted mb-3">{{ auth()->user()->email }}</p>
          <div class="mb-3">
            @forelse(auth()->user()->roles as $role)
              <span class="badge bg-primary me-1">{{ ucfirst($role->name) }}</span>
            @empty
              <span class="text-secondary">Ningún rol asignado</span>
            @endforelse
          </div>
          <a href="{{ route('users.show', auth()->user()) }}"
            class="btn btn-deepblue btn-sm me-2" title="Ver perfil">
            <i class="bi bi-eye me-1"></i>Ver perfil
          </a>
          <a href="{{ route('users.edit', auth()->user()) }}"
            class="btn btn-gold btn-sm me-2" title="Editar perfil">
            <i class="bi bi-pencil me-1"></i>Editar perfil
          </a>
          <a href="{{ route('logout') }}" 
            onclick="event.preventDefault();document.getElementById('logout-form').submit();"
            class="btn btn-red btn-sm" title="Salir">
            <i class="bi bi-box-arrow-right me-1"></i>Salir
          </a>
          <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Encabezado de página y botón Añadir usuario --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="page-header d-flex align-items-center mb-0">
      <i class="bi bi-people-fill me-2 fs-3" style="color: #e2ae76;"></i>
      <h2 class="mb-0 fw-bold" style="color: #041930;">Usuarios</h2>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-gold-blue btn-lg">
      <i class="bi bi-plus-lg me-1"></i> Añadir usuario
    </a>
  </div>

  {{-- Mensaje de éxito --}}
  @if(session('success'))
    <div class="alert alert-success mb-4 p-3 rounded-3 shadow-sm">
      <i class="bi bi-check-circle-fill me-2"></i>
      <strong>¡Éxito!</strong> {{ session('success') }}
    </div>
  @endif

  {{-- Tabla de usuarios --}}
  <div class="card border-primary shadow-sm">
    <div class="card-header" style="background-color: #041930;">
      <h5 class="mb-0" style="color: #e2ae76;">Lista de usuarios</h5>
    </div>
    <div class="card-body px-4" style="overflow-x: hidden;">
      <div class="table-responsive p-3">
        <table
          id="usersTable"
          class="table table-bordered table-striped table-hover align-middle mb-0 text-center"
          data-page-length="25"
        >
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Correo electrónico</th>
              <th>Roles</th>
              <th>Vencimiento</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($users as $u)
              @php
                $today  = Carbon::today();
                $expiry = $u->expiry_date ? Carbon::parse($u->expiry_date) : null;

                if (!$expiry) {
                    $label = '—';
                    $badge = 'bg-secondary';
                    $sortValue = 9999999; // Ordenar "sin vencimiento" al final
                } else {
                    if ($expiry->isPast()) {
                        $diff = $expiry->diffInDays($today);
                        $label = "Venció hace {$diff} días";
                        $badge = 'bg-danger blink';
                        $sortValue = -$diff; // Negativo para fechas pasadas
                    } else {
                        $diff = $today->diffInDays($expiry);
                        $label = "En {$diff} días";
                        $badge = $diff <= 7 ? 'bg-warning' : 'bg-success';
                        $sortValue = $diff; // Positivo para fechas futuras
                    }
                }
              @endphp
              <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td class="text-center">
                  @forelse($u->roles as $r)
                    <span class="badge bg-secondary">{{ ucfirst($r->name) }}</span>
                  @empty
                    <em class="text-muted">—</em>
                  @endforelse
                </td>
                <td class="text-center" data-order="{{ $sortValue }}">
                  <span class="badge {{ $badge }}">{{ $label }}</span>
                </td>
                <td class="text-center">
                  <span class="badge {{ $u->status ? 'bg-success' : 'bg-danger' }}">
                    {{ $u->status ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ route('users.show', $u) }}" class="btn btn-deepblue btn-sm me-1" title="Ver usuario">
                    <i class="bi bi-eye"></i> Ver
                  </a>
                  <a href="{{ route('users.edit', $u) }}" class="btn btn-gold btn-sm me-1" title="Editar usuario">
                    <i class="bi bi-pencil"></i> Editar
                  </a>
                  @if(auth()->user()->hasRole('super') && auth()->id() !== $u->id)
                    <form action="{{ route('users.toggleStatus', $u->id) }}" method="POST" class="d-inline me-1">
                      @csrf @method('PATCH')
                      <button type="submit" class="btn btn-sm {{ $u->status ? 'btn-red' : 'btn-deepblue' }}">
                        {{ $u->status ? 'Desactivar' : 'Activar' }}
                      </button>
                    </form>
                    <form action="{{ route('users.destroy', $u) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Eliminar este usuario?');">
                      @csrf @method('DELETE')
                      <button class="btn btn-red btn-sm">
                        <i class="bi bi-trash"></i> Eliminar
                      </button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<style>
  /* Encabezado de tabla */
  table.dataTable thead th {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    text-align: center;
    vertical-align: middle;
  }

  /* Celdas de tabla */
  table.dataTable tbody td {
    text-align: center;
    vertical-align: middle;
  }

  /* Evitar barra de desplazamiento inferior */
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
  .btn-gold-blue {
    background-color: #e2ae76 !important;
    color: #041930 !important;
    border: 1px solid #e2ae76;
  }
  .btn-gold-blue:hover {
    background-color: #d89d5c !important;
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
    border: 1px solid red !important;
    color: red !important;
    background-color: transparent !important;
  }
  .btn-red:hover {
    background-color: red !important;
    color: #fff !important;
  }

  /* Animación de parpadeo para insignias vencidas */
  @keyframes blink {
    0%, 49%   { opacity: 1; }
    50%, 100% { opacity: 0; }
  }
  .blink {
    animation: blink 1s steps(1, end) infinite;
  }

  /* Evitar que Primero/Último salten de línea */
  .dataTables_wrapper .dataTables_paginate .paginate_button {
    white-space: nowrap;
  }
</style>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.$ && $.fn.DataTable) {
    const storageKey = 'usersTableState';

    const table = $('#usersTable').DataTable({
      responsive: true,
      pageLength: 10,
      lengthChange: false,
      ordering: true,
      orderMulti: false, // Evitar ordenación por múltiples columnas
      columnDefs: [
        { orderable: false, targets: -1 } // Desactivar orden en la última columna (Acciones)
      ],
      // ✅ CORRECCIÓN CRÍTICA: activar el guardado de estado para conservar el orden de clasificación
      stateSave: true,
      stateDuration: 0, // Guardar en sessionStorage (se borra al cerrar el navegador)
      stateSaveCallback: function(settings, data) {
        try {
          sessionStorage.setItem(storageKey, JSON.stringify(data));
        } catch (e) {
          console.error('No se pudo guardar el estado de la tabla:', e);
        }
      },
      stateLoadCallback: function() {
        try {
          return JSON.parse(sessionStorage.getItem(storageKey));
        } catch (e) {
          return null;
        }
      },
      language: {
        emptyTable: "No se encontraron usuarios.",
        search: "_INPUT_",
        searchPlaceholder: "Buscar usuarios...",
        lengthMenu: "Mostrar _MENU_ elementos",
        zeroRecords: "No hay usuarios que coincidan",
        info: "Mostrando de _START_ a _END_ de _TOTAL_ elementos",
        infoEmpty: "No hay usuarios disponibles",
        paginate: {
          first: "Primero",
          last:  "Último",
          next:  "→",
          previous: "←"
        }
      }
    });

    // Evitar multiorden con Shift+clic
    $('#usersTable thead').on('mousedown', 'th', function(e) {
      if (e.shiftKey) e.preventDefault();
    });
  }

  // Tooltips de Bootstrap 5
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

  // Validación de formularios de Bootstrap
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
