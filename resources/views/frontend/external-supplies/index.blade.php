@extends('frontend.layouts.app')

@section('title','Suministros externos y devoluciones')

@section('content')
<div class="container py-5">
  <!-- Tarjeta de cabecera -->
  <div class="card shadow-sm border-0 mb-4" style="background-color: #041930;">
    <div class="card-body d-flex justify-content-between align-items-center">
      <h4 class="mb-0 fw-bold d-flex align-items-center" style="color: #e2ae76;">
        <iconify-icon
          icon="mdi:warehouse"
          class="me-2"
          style="height:1.1em; color:#e2ae76; font-size:2.1vw;">
        </iconify-icon>
        Suministros externos y devoluciones
      </h4>
      <a href="{{ route('external-supplies.create') }}" class="btn btn-lg fw-semibold d-flex align-items-center"
         style="background-color: #e2ae76; color: #041930;">
        <iconify-icon
          icon="mdi:truck-delivery"
          class="me-2"
          style="height:1.5em; color:#041930;">
        </iconify-icon>
        Añadir suministro
      </a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label for="filterClient" class="form-label fw-semibold">Filtrar por cliente</label>
          <input id="filterClient" type="text" class="form-control form-control-lg" placeholder="p. ej. Bar Jolly">
        </div>
        <div class="col-md-5">
          <label for="filterDate" class="form-label fw-semibold">Filtrar por fecha</label>
          <input id="filterDate" type="date" class="form-control form-control-lg">
        </div>
        <div class="col-md-2 text-end">
          <button class="btn btn-outline-secondary w-100"
                  onclick="document.getElementById('filterClient').value=''; document.getElementById('filterDate').value=''; applyFilters();">
            <i class="bi bi-x-circle me-1"></i> Limpiar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Acordeón -->
  <div class="accordion" id="reportAccordion">
    @php
      $rows = collect();
      foreach($all as $client => $byDates) {
        foreach($byDates as $date => $entries) {
          $rows->push(compact('client','date','entries'));
        }
      }
      $rows = $rows->sortByDesc('date')->values();
      $grp = 0;
    @endphp

    @foreach($rows as $row)
      @php
        $client    = $row['client'];
        $date      = $row['date'];
        $entries   = $row['entries'];
        $revenue   = $entries->sum('revenue');
        // El coste se sigue calculando para el beneficio del grupo (ya no se muestra por línea)
        $cost      = $entries
                      ->flatMap(fn($e) => $e['lines'])
                      ->sum(fn($l) => ($l->recipe->production_cost_per_kg ?? 0)/1000 * $l->qty);
        $profit    = $revenue - $cost;
        $type      = $entries->first()['type'];
        $collapseId= 'grp' . $grp;
      @endphp

      <div class="accordion-item client-accordion shadow-sm rounded-3 border-0 mb-3"
           data-client="{{ strtolower($client) }}" data-date="{{ $date }}">
        <h2 class="accordion-header" id="heading{{ $grp }}">
          <button class="accordion-button collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#{{ $collapseId }}"
                  aria-expanded="false"
                  style="
                    background-color: {{ $type === 'supply' ? '#041930' : '#e2ae76' }};
                    color: {{ $type === 'supply' ? '#e2ae76' : '#041930' }};
                    font-weight: bold;
                    border-radius: .5rem;
                  ">
            <div class="d-flex w-100 justify-content-between align-items-center">
              <div>
                @if($type === 'supply')
                  <i class="bi bi-truck me-1"></i> Suministro — {{ $client }} el {{ $date }}
                @else
                  <i class="bi bi-arrow-counterclockwise me-1"></i> Devolución — {{ $client }} el {{ $date }}
                @endif
              </div>
              <div class="text-end">
                <div>Ingresos:
                  <span class="badge bg-light text-dark">€{{ number_format($revenue, 2) }}</span>
                </div>
                <div>Beneficio:
                  <span class="badge {{ $profit >= 0 ? 'bg-success' : 'bg-danger' }}">
                    €{{ number_format($profit, 2) }}
                  </span>
                </div>
                <div class="progress mt-1" style="height:6px;">
                  @php
                    $pct = $revenue>0 ? ($profit/$revenue)*100 : 0;
                    $bar = min(max($pct,0),100);
                  @endphp
                  <div class="progress-bar {{ $profit>=0 ? 'bg-success' : 'bg-danger' }}"
                       role="progressbar"
                       style="width:{{ abs($bar) }}%;"></div>
                </div>
              </div>
            </div>
          </button>
        </h2>

        <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="heading{{ $grp }}" data-bs-parent="#reportAccordion">
          <div class="accordion-body bg-light">
            <table class="table table-sm table-hover" data-page-length="25">
              <thead class="table-light">
                <tr>
                  <th>Cliente</th>
                  <th>Receta</th>
                  <th>Cant.</th>
                  <th class="text-end">Ingresos línea (€)</th>
                  {{-- Eliminado: <th class="text-end">Costo línea (€)</th> --}}
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @foreach($entries as $entry)
                  @php
                    // Ya no se necesita el coste por línea en la tabla; mantenemos solo el cálculo de ingresos
                    $groupedLines = collect($entry['lines'])
                      ->groupBy(fn($l) => $l->recipe->id ?? 'x')
                      ->map(fn($g) => (object)[
                        'name'    => $g->first()->recipe->recipe_name ?? '—',
                        'qty'     => $g->sum('qty'),
                        'revenue' => ($entry['type']==='supply'?1:-1)*$g->sum('total_amount'),
                      ]);
                  @endphp
                  @foreach($groupedLines as $line)
                    <tr>
                      <td>{{ $entry['client'] }}</td>
                      <td>{{ $line->name }}</td>
                      <td>{{ $line->qty }}</td>
                      <td class="text-end">{{ number_format($line->revenue, 2) }}</td>
                      {{-- Celda de coste por línea eliminada --}}
                      <td class="text-end">
                        <a href="{{ route('returned-goods.create',['external_supply_id'=>$entry['external_supply_id']]) }}"
                           class="btn btn-sm btn-outline-warning me-1" title="Devolución">
                          <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                        <a href="{{ route('external-supplies.show',$entry['external_supply_id']) }}"
                           class="btn btn-sm btn-outline-info me-1" title="Ver">
                          <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('external-supplies.edit',$entry['external_supply_id']) }}"
                           class="btn btn-sm btn-outline-primary me-1" title="Editar">
                          <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('external-supplies.destroy',$entry['external_supply_id']) }}"
                              method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este suministro?')">
                          @csrf @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      @php $grp++; @endphp
    @endforeach
  </div>
</div>
@endsection

@section('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const clientFilter = document.getElementById('filterClient');
    const dateFilter   = document.getElementById('filterDate');
    const items        = document.querySelectorAll('.client-accordion');

    window.applyFilters = function() {
      const c = clientFilter.value.trim().toLowerCase();
      const d = dateFilter.value;
      items.forEach(item => {
        const okC = !c || item.dataset.client.includes(c);
        const okD = !d || item.dataset.date === d;
        item.style.display = (okC && okD) ? '' : 'none';
      });
    }

    clientFilter.addEventListener('input', applyFilters);
    dateFilter.addEventListener('input', applyFilters);
  });
</script>
@endsection
