@extends('frontend.layouts.app')

@section('title','Tutte le Entrate')

@section('content')
<div class="container py-5 px-md-5">

  <!-- Aggiungi / Modifica Entrata -->
  <div class="card mb-5 border-success shadow-sm">
    <div class="card-header d-flex align-items-center" style="background-color: #041930;">
      <h5 class="mb-0 fw-bold d-flex align-items-center" style="color: #e2ae76; font-size: 1.7vw;">
        <iconify-icon icon="mdi:currency-eur"
          style="margin-right:0;height:1.1em;font-size:1.7vw;color:#e2ae76;">
        </iconify-icon>
        {{ isset($income) ? 'Modifica Entrata' : 'Aggiungi Entrata' }}
      </h5>
    </div>
    <div class="card-body">
      <form
        action="{{ isset($income) ? route('incomes.update', $income) : route('incomes.store') }}"
        method="POST"
        class="row g-3 needs-validation"
        novalidate
      >
        @csrf
        @if(isset($income)) @method('PUT') @endif

        <div class="col-md-6">
          <label for="identifier" class="form-label fw-semibold">
            Identificatore <small class="text-muted">(facoltativo)</small>
          </label>
          <input type="text" name="identifier" id="identifier"
                 class="form-control form-control-lg"
                 value="{{ old('identifier', $income->identifier ?? '') }}">
        </div>

        <div class="col-md-6">
          <label for="amount" class="form-label fw-semibold">Importo (€)</label>
          <div class="input-group input-group-lg has-validation">
            <span class="input-group-text"><i class="bi bi-currency-euro"></i></span>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control"
                   value="{{ old('amount', $income->amount ?? '') }}" required>
            <div class="invalid-feedback">
              {{ $errors->first('amount', 'Inserisci un importo valido.') }}
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <label for="date" class="form-label fw-semibold">Data</label>
          <input type="date" name="date" id="date" class="form-control form-control-lg"
                 value="{{ old('date', isset($income) ? $income->date->format('Y-m-d') : '') }}" required>
          <div class="invalid-feedback">
            {{ $errors->first('date', 'Seleziona una data.') }}
          </div>
        </div>

        <!-- Categoria dinamica -->
        <div class="col-md-6">
          <label for="income_category_id" class="form-label fw-semibold">Categoria Entrata</label>
          <select name="income_category_id" id="income_category_id" class="form-select form-select-lg">
            <option value="">Seleziona Categoria</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}"
                @selected(old('income_category_id', $income->income_category_id ?? null) == $cat->id)>
                {{ $cat->name }}
              </option>
            @endforeach
          </select>
          <div class="invalid-feedback">Seleziona una categoria valida.</div>
        </div>

        <div class="col-12 text-end">
          <button type="submit" class="btn btn-gold-save btn-lg">
            <i class="bi bi-save2 me-1"></i>
            {{ isset($income) ? 'Aggiorna Entrata' : 'Salva Entrata' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Filtra per Mese -->
  <div class="row g-2 align-items-end mb-4">
    <div class="col-auto">
      <label for="filterMonth" class="form-label fw-semibold">Mostra mese</label>
      <input type="month" id="filterMonth" class="form-control form-control-lg"
             value="{{ now()->format('Y-m') }}">
    </div>
  </div>

  <!-- Tabella Entrate Registrate -->
  <div class="card border-success shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between"
         style="background-color:#041930;color:#e2ae76;">
      <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Entrate Registrate</h5>
    </div>
    <div class="card-body table-responsive">
      <table  data-page-length="25" id="incomesTable"
             class="table table-bordered table-striped table-hover align-middle text-center mb-0"
             data-page-length="25">
        <thead>
          <tr>
            <th class="sortable">Identificatore <span class="sort-indicator"></span></th>
            <th class="sortable">Data <span class="sort-indicator"></span></th>
            <th class="sortable">Importo (€) <span class="sort-indicator"></span></th>
            <th class="sortable">Categoria <span class="sort-indicator"></span></th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          @forelse($incomes as $inc)
            <tr>
              <td>{{ $inc->identifier ?? '—' }}</td>

              {{-- IMPORTANT: store machine-friendly date in data-order --}}
              <td data-order="{{ $inc->date->format('Y-m-d') }}">{{ $inc->date->format('Y-m-d') }}</td>

              {{-- numeric data-order for correct sorting --}}
              <td data-order="{{ $inc->amount }}">€{{ number_format($inc->amount, 2) }}</td>

              {{-- show category name --}}
              <td>{{ optional($inc->category)->name ?? '—' }}</td>

              <td class="text-center">
                <a href="{{ route('incomes.show', $inc) }}" class="btn btn-sm btn-deepblue me-1" title="Visualizza Entrata">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="{{ route('incomes.edit', $inc) }}" class="btn btn-sm btn-gold me-1" title="Modifica Entrata">
                  <i class="bi bi-pencil"></i>
                </a>
                <form action="{{ route('incomes.destroy', $inc) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Eliminare questa entrata?');">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-red" title="Elimina Entrata">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">Nessuna entrata registrata.</td></tr>
          @endforelse
        </tbody>
      </table>

      <div class="mt-3"></div>
    </div>
  </div>

</div>
@endsection

<style>
  table th {
    background-color:#e2ae76!important;color:#041930!important;text-align:center;vertical-align:middle;position:relative;
  }
  table td { text-align:center; vertical-align:middle; }
  #incomesTable thead th.sortable{ cursor:pointer; user-select:none; white-space:nowrap; }
  #incomesTable thead th .sort-indicator{ display:inline-block; width:14px; text-align:center; font-size:.7rem; line-height:1; margin-left:4px; color:#041930; opacity:0; transition:opacity .15s; }
  #incomesTable thead th[data-sort-dir] .sort-indicator{ opacity:1; }

  table.dataTable thead .sorting:after,
  table.dataTable thead .sorting_asc:after,
  table.dataTable thead .sorting_desc:after,
  table.dataTable thead .sorting:before,
  table.dataTable thead .sorting_asc:before,
  table.dataTable thead .sorting_desc:before { content:''!important; }

  .btn-gold-save{ border:1px solid #e2ae76!important;color:#041930!important;background-color:#e2ae76!important; }
  .btn-gold-save:hover{ background-color:#d89d5c!important;color:#fff!important; }
  .btn-gold,.btn-deepblue,.btn-red{ border:1px solid; background:transparent!important; font-weight:500; }
  .btn-gold{ border-color:#e2ae76!important;color:#e2ae76!important; }
  .btn-gold:hover{ background-color:#e2ae76!important;color:#fff!important; }
  .btn-deepblue{ border-color:#041930!important;color:#041930!important; }
  .btn-deepblue:hover{ background-color:#041930!important;color:#fff!important; }
  .btn-red{ border-color:red!important;color:red!important; }
  .btn-red:hover{ background-color:red!important;color:#fff!important; }
</style>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // ensure jQuery + DataTables exist
  if (window.$ && $.fn.DataTable) {
    // Silence DT errors in UI
    $.fn.dataTable.ext.errMode = 'none';

    const STORAGE_KEY = 'incomes_sort_state';

    // Init DataTable
    const table = $('#incomesTable').DataTable({
      paging: true,
      ordering: true,
      orderMulti: false, // single-column ordering only
      responsive: true,
      pageLength: $('#incomesTable').data('page-length') || 10,
      order: [[1, 'desc']], // default: sort by Date desc (column index 1)
      columnDefs: [
        { orderable: false, targets: 4 } // Azioni not orderable (zero-based index)
      ],
      language: {
        search: "Cerca:",
        lengthMenu: "Mostra _MENU_ voci",
        info: "Visualizzati da _START_ a _END_ di _TOTAL_ entrate",
        paginate: { previous: "«", next: "»" },
        zeroRecords: "Nessuna entrata trovata"
      }
    });

    // Restore previously saved 2-state sort (if any)
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) {
        const { col, dir } = JSON.parse(saved);
        if (typeof col === 'number' && (dir === 'asc' || dir === 'desc')) {
          table.order([col, dir]).draw(false); // draw(false) keeps paging
        }
      }
    } catch (e) {
      // ignore restore errors
    }

    // Update custom ▲/▼ indicators
    function updateIndicators() {
      $('#incomesTable thead th.sortable')
        .removeAttr('data-sort-dir')
        .find('.sort-indicator').text('');
      const ord = table.order();
      if (!ord.length) return;
      const col = ord[0][0];
      const dir = ord[0][1];
      const th = $('#incomesTable thead th').eq(col);
      if (!th.hasClass('sortable')) return;
      th.attr('data-sort-dir', dir);
      th.find('.sort-indicator').text(dir === 'asc' ? '▲' : '▼');
    }

    // keep indicators in sync on order and draw
    table.on('order.dt draw.dt', updateIndicators);
    // initial call (in case DataTable already drew)
    updateIndicators();

    // 2-state header click handler (asc <-> desc only)
    $('#incomesTable thead').on('click', 'th.sortable', function() {
      const idx = $(this).index();
      const colSettings = table.settings()[0].aoColumns[idx];
      if (colSettings && colSettings.bSortable === false) return;

      const current = table.order();
      const currentCol = current.length ? current[0][0] : null;
      const currentDir = current.length ? current[0][1] : 'asc';
      const newDir = (currentCol === idx && currentDir === 'asc') ? 'desc' : 'asc';

      table.order([idx, newDir]).draw();

      // persist
      try {
        const ord = table.order();
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ col: ord[0][0], dir: ord[0][1] }));
      } catch (e) {}
    });

    // Prevent shift multi-order
    $('#incomesTable thead').on('mousedown', 'th', function(e) {
      if (e.shiftKey) e.preventDefault();
    });

    // Month filter using data-order attribute for robustness
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
      if (settings.nTable.id !== 'incomesTable') return true;
      const selected = $('#filterMonth').val();
      if (!selected) return true;

      // get the row node and read the data-order attribute of the date cell (col index 1)
      const rowNode = table.row(dataIndex).node();
      if (!rowNode) return true;
      const dateVal = $(rowNode).find('td').eq(1).attr('data-order') || $(rowNode).find('td').eq(1).text();
      // dateVal expected like 'YYYY-MM-DD' — compare YYYY-MM
      return dateVal && dateVal.substr(0, 7) === selected;
    });

    $('#filterMonth').on('change', function() {
      table.draw();
    });
  }

  // Bootstrap validation
  document.querySelectorAll('.needs-validation').forEach(form => {
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
