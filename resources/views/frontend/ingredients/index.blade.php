@extends('frontend.layouts.app')

@section('title', 'Ingredienti')

@section('content')
<div class="container py-5 px-md-5">

  {{-- ══ 1. UPLOAD INVOICE CARD ══════════════════════════════════════════════ --}}
  <div class="card shadow-sm mb-4" style="border-left:4px solid #e2ae76;">
    <div class="card-header d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#041930,#093060);border-radius:.5rem .5rem 0 0;">
      <i class="bi bi-cloud-arrow-up-fill" style="color:#e2ae76;font-size:1.5rem;"></i>
      <h5 class="mb-0 fw-bold" style="color:#e2ae76;">Carica Fattura — Estrazione AI Automatica</h5>
      <span class="ms-auto badge fw-normal" style="background:#e2ae76;color:#041930;font-size:.75rem;">
        Google Vision + GPT-4o
      </span>
    </div>
    <div class="card-body p-4">
      <div id="dropZone"
           class="rounded-3 text-center p-5"
           style="border:2px dashed #e2ae76;background:#fdfaf5;cursor:pointer;transition:background .2s;"
           onclick="document.getElementById('invoiceFileInput').click()"
           ondragover="event.preventDefault();this.style.background='#f0e6d0';"
           ondragleave="this.style.background='#fdfaf5';"
           ondrop="handleInvDrop(event)">
        <div id="dzContent">
          <i class="bi bi-file-earmark-arrow-up" style="font-size:3.5rem;color:#e2ae76;"></i>
          <p class="mt-3 mb-1 fw-semibold fs-5" style="color:#041930;">Clicca o trascina la fattura qui</p>
          <p class="text-muted mb-0 small">JPG, PNG, WEBP, PDF — max 20 MB</p>
        </div>
        <div id="dzLoading" class="d-none">
          <div class="spinner-border mb-3" style="width:3.5rem;height:3.5rem;color:#e2ae76;" role="status"></div>
          <p class="fw-semibold fs-5 mb-1" style="color:#041930;">Elaborazione in corso...</p>
          <p class="text-muted small mb-0" id="dzLoadingStep">Analisi OCR con Google Vision...</p>
        </div>
      </div>
      <input type="file" id="invoiceFileInput" accept=".jpg,.jpeg,.png,.webp,.pdf"
             class="d-none" onchange="handleInvFileSelect(this)">
      <div id="invUploadErr" class="alert alert-danger mt-3 d-none" role="alert"></div>
    </div>
  </div>


  {{-- ══ 2. ADD / EDIT INGREDIENT FORM ══════════════════════════════════════ --}}
  <div class="card border-primary shadow-sm mb-5">
    <div class="card-header d-flex align-items-center"
         style="background:#041930;padding:.75rem 1.25rem;border-radius:.5rem .5rem 0 0;">
      <h5 class="mb-0 fw-bold" style="color:#e2ae76;">
        {{ isset($ingredient) ? 'Modifica Ingrediente' : 'Aggiungi Ingrediente' }}
      </h5>
    </div>
    <div class="card-body">
      <form
        action="{{ isset($ingredient) ? route('ingredients.update', $ingredient) : route('ingredients.store') }}"
        method="POST"
        class="row g-3 needs-validation"
        id="ingredientForm"
        novalidate
      >
        @csrf
        @if(isset($ingredient)) @method('PUT') @endif

        {{-- Name --}}
        <div class="col-md-4">
          <label for="ingredientName" class="form-label fw-semibold">Nome Principale</label>
          <input type="text" id="ingredientName" name="ingredient_name"
                 class="form-control form-control-lg @error('ingredient_name') is-invalid @enderror"
                 placeholder="es. Farina 00"
                 value="{{ old('ingredient_name', $ingredient->ingredient_name ?? '') }}" required>
          <div class="invalid-feedback">
            @error('ingredient_name'){{ $message }}@else Inserisci un nome. @enderror
          </div>
        </div>

        {{-- Price --}}
        <div class="col-md-4">
          <label for="pricePerKg" class="form-label fw-semibold">Prezzo al kg</label>
          <div class="input-group input-group-lg has-validation">
            <span class="input-group-text">€</span>
            <input type="number" id="pricePerKg" name="price_per_kg"
                   class="form-control @error('price_per_kg') is-invalid @enderror"
                   step="0.0001" placeholder="0.0000"
                   value="{{ old('price_per_kg', $ingredient->price_per_kg ?? '') }}" required>
            <span class="input-group-text">/kg</span>
            <div class="invalid-feedback">
              @error('price_per_kg'){{ $message }}@else Inserisci un prezzo valido. @enderror
            </div>
          </div>
        </div>

        {{-- ── DYNAMIC ALIASES ─────────────────────────────────────────────── --}}
        <div class="col-md-4">
          <label class="form-label fw-semibold">
            Nomi Alternativi (alias)
            <i class="bi bi-info-circle text-muted ms-1"
               data-bs-toggle="tooltip"
               title="Ogni alias viene usato per il matching automatico delle fatture."></i>
          </label>

          {{-- Hidden field serialised before submit --}}
          <input type="hidden" name="additional_names_raw" id="additionalNamesRaw">

          @php
            $existingAliases = isset($ingredient) && is_array($ingredient->additional_names ?? null) && count($ingredient->additional_names)
                ? $ingredient->additional_names
                : (old('additional_names_raw') ? array_filter(array_map('trim', explode(',', old('additional_names_raw')))) : []);
          @endphp

          <div id="aliasesWrapper" class="d-flex flex-column gap-2">
            @forelse($existingAliases as $alias)
              <div class="alias-row input-group">
                <input type="text" class="form-control alias-input"
                       placeholder="es. White Sugar"
                       value="{{ trim($alias) }}">
                <button type="button" class="btn btn-outline-danger btn-sm alias-remove-btn"
                        onclick="removeAliasRow(this)" title="Rimuovi">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            @empty
              {{-- Always render at least one empty row so the section is visible --}}
              <div class="alias-row input-group">
                <input type="text" class="form-control alias-input"
                       placeholder="es. White Sugar" value="">
                <button type="button" class="btn btn-outline-danger btn-sm alias-remove-btn"
                        onclick="removeAliasRow(this)" title="Rimuovi">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            @endforelse
          </div>

          <button type="button" class="btn btn-sm mt-2 btn-add-alias"
                  onclick="addAliasRow()">
            <i class="bi bi-plus-circle me-1"></i>Aggiungi alias
          </button>
          <div class="form-text text-muted mt-1">Uno per riga — opzionale</div>
        </div>
        {{-- ── END DYNAMIC ALIASES ─────────────────────────────────────────── --}}

        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
          @if(isset($ingredient))
            <a href="{{ route('ingredients.index') }}" class="btn btn-lg btn-outline-secondary">Annulla</a>
          @endif
          <button type="submit" class="btn btn-lg fw-semibold"
                  style="background:#e2ae76;color:#041930;border-color:#e2ae76;">
            <i class="bi bi-save2 me-2"></i>
            {{ isset($ingredient) ? 'Aggiorna Ingrediente' : 'Salva Ingrediente' }}
          </button>
        </div>
      </form>
    </div>
  </div>


  {{-- ══ 3. INGREDIENTS TABLE ════════════════════════════════════════════════ --}}
  <div class="card border-primary shadow-sm">
    <div class="card-header" style="background:#041930;">
      <h5 class="mb-0" style="color:#e2ae76;">Vetrina Ingredienti</h5>
    </div>
    <div class="card-body px-4">
      <div class="table-responsive p-2">
        <table id="ingredientsTable"
               class="table table-bordered table-striped table-hover align-middle mb-0 text-center"
               data-page-length="25">
          <thead>
            <tr>
              <th class="sortable">Nome <span class="sort-indicator"></span></th>
              <th class="sortable">Prezzo / kg <span class="sort-indicator"></span></th>
              <th>Nomi Alternativi</th>
              <th class="sortable">Ultimo agg. <span class="sort-indicator"></span></th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            @foreach($ingredients as $ing)
            <tr>
              <td class="fw-semibold text-start ps-3">{{ $ing->ingredient_name }}</td>
              <td data-order="{{ $ing->price_per_kg }}">€{{ number_format($ing->price_per_kg, 4) }}</td>
              <td>
                @forelse($ing->additional_names ?? [] as $alias)
                  <span class="badge me-1 mb-1" style="background:#e2ae76;color:#041930;">{{ $alias }}</span>
                @empty
                  <span class="text-muted small">—</span>
                @endforelse
              </td>
              <td data-order="{{ $ing->updated_at->format('Y-m-d H:i') }}">
                {{ $ing->updated_at->format('Y-m-d H:i') }}
              </td>
              <td>
                <a href="{{ route('ingredients.edit', $ing) }}"
                   class="btn btn-sm btn-gold me-1" title="Modifica">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="{{ route('ingredients.show', $ing) }}"
                   class="btn btn-sm btn-deepblue me-1" title="Visualizza">
                  <i class="bi bi-eye"></i>
                </a>
                <button type="button" class="btn btn-sm btn-tags me-1" title="Gestisci Alias"
                        onclick="openAliasModal({{ $ing->id }}, @js($ing->ingredient_name), @js($ing->additional_names ?? []))">
                  <i class="bi bi-tags"></i>
                </button>
                <form action="{{ route('ingredients.destroy', $ing) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Eliminare «{{ $ing->ingredient_name }}»?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-red" title="Elimina">
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

</div>{{-- /container --}}


{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL 1 — Invoice Preview & Edit
══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="invoicePreviewModal" tabindex="-1"
     aria-labelledby="invPreviewTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:#041930;">
        <h5 class="modal-title fw-bold" id="invPreviewTitle" style="color:#e2ae76;">
          <i class="bi bi-file-earmark-check me-2"></i>
          Anteprima Fattura — Verifica e Correggi Prima di Salvare
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-3 mb-4 align-items-end">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Fornitore / Venditore</label>
            <input type="text" id="prevSupplier" class="form-control form-control-lg"
                   placeholder="Nome fornitore">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Data Fattura</label>
            <input type="date" id="prevDate" class="form-control form-control-lg">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold">Codice Fattura</label>
            <input type="text" id="prevInvoiceCode" class="form-control form-control-lg"
                   placeholder="es. FAC-2025-001234">
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded" style="background:#f8f4ee;border:1px solid #e2ae76;">
              <p class="mb-0 small fw-semibold" style="color:#041930;" id="prevMatchSummary">
                <i class="bi bi-info-circle me-1"></i>Caricamento...
              </p>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle text-center mb-0" id="prevItemsTable">
            <thead style="background:#e2ae76;color:#041930;">
              <tr>
                <th style="width:28%;">Nome Ingrediente</th>
                <th style="width:20%;">Prezzo / kg (€)</th>
                <th style="width:12%;">Totale Riga (€)</th>
                <th style="width:22%;">Valore Originale Fattura</th>
                <th style="width:12%;">Stato</th>
                <th style="width:6%;">✕</th>
              </tr>
            </thead>
            <tbody id="prevItemsBody"></tbody>
            <tfoot>
              <tr style="background:#f8f4ee;">
                <td colspan="2" class="text-end fw-bold">Totale Fattura Ingredienti:</td>
                <td class="fw-bold text-success" id="invGrandTotal">€0.0000</td>
                <td colspan="3" class="text-muted small fst-italic">
                  <i class="bi bi-info-circle me-1"></i>Verrà registrato nella tabella Costi
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-3 d-flex gap-2 align-items-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addManualPrevRow()">
            <i class="bi bi-plus-circle me-1"></i>Aggiungi Riga Manuale
          </button>
          <span class="text-muted small ms-2">
            <i class="bi bi-lightbulb me-1"></i>
            Verifica ogni prezzo prima di confermare — modifica direttamente nelle celle.
          </span>
        </div>

        <div class="mt-4">
          <button class="btn btn-sm btn-outline-secondary" type="button"
                  data-bs-toggle="collapse" data-bs-target="#rawOcrCollapse">
            <i class="bi bi-code-square me-1"></i>Mostra testo OCR grezzo
          </button>
          <div class="collapse mt-2" id="rawOcrCollapse">
            <pre id="rawOcrContent"
                 class="p-3 rounded small text-start"
                 style="background:#f5f5f5;max-height:220px;overflow-y:auto;white-space:pre-wrap;font-size:.75rem;"></pre>
          </div>
        </div>
      </div>

      <div class="modal-footer" style="background:#f8f4ee;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" id="confirmInvBtn" onclick="submitInvoiceData()"
                class="btn btn-lg fw-bold"
                style="background:#041930;color:#e2ae76;border-color:#041930;">
          <i class="bi bi-check2-circle me-2"></i>Conferma e Salva Ingredienti
        </button>
      </div>
    </div>
  </div>
</div>


{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL 2 — Processing Result
══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="invoiceResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:#041930;">
        <h5 class="modal-title fw-bold" style="color:#e2ae76;">
          <i class="bi bi-check-circle me-2"></i>Risultato Elaborazione
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="resultModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn fw-semibold" style="background:#e2ae76;color:#041930;"
                onclick="location.reload()">
          <i class="bi bi-arrow-clockwise me-2"></i>Aggiorna Pagina
        </button>
      </div>
    </div>
  </div>
</div>


{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL 3 — Manage Aliases
══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="aliasModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:#041930;">
        <h5 class="modal-title fw-bold" style="color:#e2ae76;">
          <i class="bi bi-tags me-2"></i>Gestisci Nomi Alternativi
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="fw-bold mb-3" id="aliasIngNameLabel" style="color:#041930;"></p>
        <input type="hidden" id="aliasIngIdHidden">

        <label class="form-label fw-semibold text-muted small">
          Aggiungi tutti i nomi con cui questo ingrediente può apparire sulle fatture:
        </label>

        <div id="modalAliasesWrapper" class="d-flex flex-column gap-2 mb-2"></div>

        <button type="button" class="btn btn-sm btn-add-alias"
                onclick="addModalAliasRow()">
          <i class="bi bi-plus-circle me-1"></i>Aggiungi alias
        </button>

        <div class="mt-3 p-3 rounded small" style="background:#f8f4ee;border:1px solid #e2ae76;">
          <i class="bi bi-info-circle me-1" style="color:#e2ae76;"></i>
          <strong>Come funziona:</strong> Quando carichi una fattura e un fornitore scrive
          il nome diversamente (es. "White Sugar" invece di "Zucchero"), il sistema lo
          riconoscerà come lo stesso ingrediente.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="button" class="btn fw-semibold" onclick="saveAliasesAjax()"
                style="background:#e2ae76;color:#041930;">
          <i class="bi bi-save2 me-1"></i>Salva Alias
        </button>
      </div>
    </div>
  </div>
</div>

@endsection


{{-- ══ STYLES ══════════════════════════════════════════════════════════════ --}}
<style>
table.dataTable thead th {
  background-color: #e2ae76 !important;
  color: #041930 !important;
  text-align: center;
  vertical-align: middle;
}
#ingredientsTable thead th.sortable { cursor:pointer;user-select:none;position:relative;white-space:nowrap; }
#ingredientsTable thead th .sort-indicator {
  display:inline-block;width:14px;text-align:center;font-size:.7rem;
  line-height:1;margin-left:4px;color:#041930;opacity:0;transition:opacity .15s;
}
#ingredientsTable thead th[data-sort-dir] .sort-indicator { opacity:1; }
table.dataTable thead .sorting:after,table.dataTable thead .sorting_asc:after,
table.dataTable thead .sorting_desc:after,table.dataTable thead .sorting:before,
table.dataTable thead .sorting_asc:before,table.dataTable thead .sorting_desc:before
{ content:'' !important; }
table.dataTable tbody td { text-align:center;vertical-align:middle; }

.btn-gold      { border:1px solid #e2ae76!important;color:#e2ae76!important;background:transparent!important; }
.btn-gold:hover{ background:#e2ae76!important;color:#fff!important; }
.btn-deepblue  { border:1px solid #041930!important;color:#041930!important;background:transparent!important; }
.btn-deepblue:hover { background:#041930!important;color:#fff!important; }
.btn-red       { border:1px solid #dc3545!important;color:#dc3545!important;background:transparent!important; }
.btn-red:hover { background:#dc3545!important;color:#fff!important; }
.btn-tags      { border:1px solid #6f42c1!important;color:#6f42c1!important;background:transparent!important; }
.btn-tags:hover{ background:#6f42c1!important;color:#fff!important; }

/* ── Alias "Add" button ── */
.btn-add-alias {
  border: 1px dashed #e2ae76 !important;
  color: #e2ae76 !important;
  background: transparent !important;
}
.btn-add-alias:hover { background: #fdf3e3 !important; }

/* ── Alias input rows ── */
#aliasesWrapper,
#modalAliasesWrapper {
  min-height: 44px; /* always visible even when empty */
}
.alias-row { align-items: center; }
.alias-row .form-control {
  border-color: #e2ae76;
  height: 38px;
}
.alias-row .form-control:focus {
  border-color: #c9954d;
  box-shadow: 0 0 0 .2rem rgba(226,174,118,.25);
}
.alias-row .alias-remove-btn {
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 .6rem;
}

.dataTables_length select {
  border:1px solid #e2ae76;color:#041930;padding-right:30px;
  background:#fff url('data:image/svg+xml;utf8,<svg fill="%23e2ae76" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center;
  appearance:none;
}
#dropZone:hover { background:#f5ede0 !important; }
#prevItemsTable input.form-control { min-width:100px; }
.orig-calc { font-size:.78rem;line-height:1.4; }
.orig-calc .calc-formula { color:#198754;font-weight:600; }
.orig-calc .calc-raw     { color:#6c757d; }
</style>


{{-- ══ SCRIPTS ══════════════════════════════════════════════════════════════ --}}
@section('scripts')
<script>
'use strict';

const EXISTING_INGS = @json($ingredientsForJs);

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 1 — UPLOAD / DROP HANDLERS
═════════════════════════════════════════════════════════════════════════ */

function handleInvDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').style.background = '#fdfaf5';
  const file = e.dataTransfer.files[0];
  if (file) processInvFile(file);
}

function handleInvFileSelect(input) {
  const file = input.files[0];
  if (file) processInvFile(file);
  input.value = '';
}

async function processInvFile(file) {
  const allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
  if (!allowed.includes(file.type)) { showInvErr('Tipo file non supportato.'); return; }
  if (file.size > 20*1024*1024)     { showInvErr('File troppo grande. Massimo 20 MB.'); return; }

  hideInvErr();
  showDzLoading('Analisi OCR con Google Vision...');

  const fd = new FormData();
  fd.append('invoice', file);
  fd.append('_token', csrfToken());

  try {
    const resp = await fetch('{{ route("ingredients.extractInvoice") }}', { method:'POST', body:fd });
    setDzStep('Parsing intelligente con GPT-4o...');
    const data = await resp.json();
    hideDzLoading();
    if (!resp.ok || data.error) { showInvErr('⚠ '+(data.error||'Errore sconosciuto.')); return; }
    showPrevModal(data);
  } catch (err) {
    hideDzLoading();
    showInvErr('Errore di rete: '+err.message);
  }
}

function showDzLoading(step) {
  document.getElementById('dzContent').classList.add('d-none');
  document.getElementById('dzLoading').classList.remove('d-none');
  setDzStep(step);
}
function hideDzLoading() {
  document.getElementById('dzContent').classList.remove('d-none');
  document.getElementById('dzLoading').classList.add('d-none');
}
function setDzStep(s)  { document.getElementById('dzLoadingStep').textContent = s; }
function showInvErr(m) {
  const el = document.getElementById('invUploadErr');
  el.textContent = m; el.classList.remove('d-none');
}
function hideInvErr() { document.getElementById('invUploadErr').classList.add('d-none'); }

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 2 — PREVIEW MODAL: POPULATE
═════════════════════════════════════════════════════════════════════════ */

function showPrevModal(data) {
  document.getElementById('prevSupplier').value    = data.supplier_name || '';
  document.getElementById('prevDate').value        = data.date          || '';
  document.getElementById('prevInvoiceCode').value = data.invoice_code  || '';
  document.getElementById('rawOcrContent').textContent = data.raw_text  || '';

  const tbody = document.getElementById('prevItemsBody');
  tbody.innerHTML = '';

  if (!data.items || data.items.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-muted py-4 text-center">
      <i class="bi bi-exclamation-circle me-2"></i>
      Nessun ingrediente estratto. Aggiungi righe manualmente.
    </td></tr>`;
  } else {
    data.items.forEach(item => appendPrevRow(item));
  }

  updateMatchSummary();
  updateGrandTotal();
  new bootstrap.Modal(document.getElementById('invoicePreviewModal')).show();
}

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 3 — PREVIEW MODAL: ROW MANAGEMENT
═════════════════════════════════════════════════════════════════════════ */

function appendPrevRow(item) {
  const tbody     = document.getElementById('prevItemsBody');
  const match     = matchExisting(item.name || '');
  const lineTotal = parseFloat(item.original_price || 0);

  let origHtml = '';
  const rawStr  = item.original_price_raw ? escH(item.original_price_raw) : fmtP(item.original_price || item.price_per_kg);
  const origUnit = escH(item.original_unit || 'kg');

  if (item.notes && item.notes !== '') {
    origHtml = `<div class="orig-calc">
      <div class="calc-raw">${rawStr} / ${origUnit}</div>
      <div class="calc-formula">${escH(item.notes)}</div>
    </div>`;
  } else {
    origHtml = `<div class="orig-calc"><div class="calc-raw">${rawStr} / ${origUnit}</div></div>`;
  }

  const tr = document.createElement('tr');
  tr.dataset.lineTotal = lineTotal.toFixed(4);

  tr.innerHTML = `
    <td>
      <input type="text" class="form-control form-control-sm item-name fw-semibold"
             value="${escH(item.name||'')}" oninput="onPrevNameInput(this)"
             placeholder="Nome ingrediente" required>
    </td>
    <td>
      <div class="input-group input-group-sm">
        <span class="input-group-text">€</span>
        <input type="number" class="form-control item-price text-center"
               value="${fmtP(item.price_per_kg||0)}" step="0.0001" min="0.0001"
               oninput="updateGrandTotal()" required>
        <span class="input-group-text">/kg</span>
      </div>
    </td>
    <td>
      <div class="input-group input-group-sm">
        <span class="input-group-text">€</span>
        <input type="number" class="form-control item-line-total text-center"
               value="${lineTotal.toFixed(4)}" step="0.0001" min="0"
               oninput="onLineTotalChange(this)">
      </div>
    </td>
    <td class="small">${origHtml}</td>
    <td class="match-cell">${matchBadge(match)}</td>
    <td>
      <button type="button" class="btn btn-sm btn-red"
              onclick="removePrevRow(this)" title="Rimuovi">
        <i class="bi bi-x-lg"></i>
      </button>
    </td>
  `;
  tbody.appendChild(tr);
}

function addManualPrevRow() {
  appendPrevRow({ name:'',price_per_kg:0,original_unit:'kg',original_qty:1,original_price:0,original_price_raw:'',notes:'' });
  const rows = document.querySelectorAll('#prevItemsBody tr');
  rows[rows.length-1].querySelector('.item-name')?.focus();
}

function removePrevRow(btn) {
  btn.closest('tr').remove();
  updateMatchSummary();
  updateGrandTotal();
}

function onPrevNameInput(input) {
  const match = matchExisting(input.value);
  input.closest('tr').querySelector('.match-cell').innerHTML = matchBadge(match);
  updateMatchSummary();
}

function onLineTotalChange(input) {
  input.closest('tr').dataset.lineTotal = parseFloat(input.value||0).toFixed(4);
  updateGrandTotal();
}

function matchBadge(match) {
  if (!match) return '<span class="badge bg-success"><i class="bi bi-plus-lg me-1"></i>Nuovo</span>';
  return `<span class="badge bg-warning text-dark" title="Verrà aggiornato: ${escH(match.name)}">
    <i class="bi bi-arrow-repeat me-1"></i>Aggiorna<br>
    <small class="fw-normal">${escH(match.name)}</small>
  </span>`;
}

function updateMatchSummary() {
  let news=0, updates=0, empty=0;
  document.querySelectorAll('#prevItemsBody tr').forEach(tr => {
    const ni = tr.querySelector('.item-name');
    if (!ni) return;
    const v = ni.value.trim();
    if (!v) empty++;
    else if (matchExisting(v)) updates++;
    else news++;
  });
  let html = '<i class="bi bi-info-circle me-1"></i>';
  if (empty) html += `<span class="text-danger me-2">⚠ ${empty} senza nome</span>`;
  html += `<span class="text-success me-3">✚ ${news} nuovi</span>`;
  html += `<span style="color:#856404;">↻ ${updates} aggiornamenti</span>`;
  document.getElementById('prevMatchSummary').innerHTML = html;
}

function updateGrandTotal() {
  let total = 0;
  document.querySelectorAll('#prevItemsBody tr').forEach(tr => {
    total += parseFloat(tr.dataset.lineTotal || 0);
  });
  document.getElementById('invGrandTotal').textContent = '€' + total.toFixed(4);
}

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 4 — SUBMIT CONFIRMED INVOICE DATA
═════════════════════════════════════════════════════════════════════════ */

async function submitInvoiceData() {
  const rows  = document.querySelectorAll('#prevItemsBody tr');
  const items = [];

  for (const tr of rows) {
    const ni = tr.querySelector('.item-name');
    const pi = tr.querySelector('.item-price');
    const li = tr.querySelector('.item-line-total');
    if (!ni || !pi) continue;

    const name      = ni.value.trim();
    const price     = parseFloat(pi.value);
    const lineTotal = parseFloat(li?.value || tr.dataset.lineTotal || 0);

    if (!name) {
      ni.classList.add('is-invalid');
      ni.scrollIntoView({ behavior:'smooth', block:'center' });
      ni.focus();
      alert('Tutti i nomi ingrediente sono obbligatori.');
      return;
    }
    if (!price || price <= 0) {
      pi.classList.add('is-invalid');
      pi.scrollIntoView({ behavior:'smooth', block:'center' });
      pi.focus();
      alert(`Prezzo non valido per: ${name}`);
      return;
    }
    ni.classList.remove('is-invalid');
    pi.classList.remove('is-invalid');
    items.push({ name, price_per_kg: price, line_total: lineTotal });
  }

  if (items.length === 0) { alert('Nessun ingrediente da salvare.'); return; }

  const btn = document.getElementById('confirmInvBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvataggio...';

  try {
    const resp = await fetch('{{ route("ingredients.processInvoice") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN':  csrfToken(),
        'Accept':        'application/json',
      },
      body: JSON.stringify({
        supplier_name: document.getElementById('prevSupplier').value,
        invoice_code:  document.getElementById('prevInvoiceCode').value,
        date:          document.getElementById('prevDate').value,
        items,
      }),
    });

    const data = await resp.json();
    bootstrap.Modal.getInstance(document.getElementById('invoicePreviewModal'))?.hide();

    let html = '';
    if (data.success) {
      html += `<div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-2"></i>${escH(data.message)}
      </div>`;
      if (data.cost_entry) {
        html += `<div class="alert alert-info py-2">
          <i class="bi bi-receipt me-2"></i>
          <strong>Costo registrato:</strong> €${fmtP(data.cost_entry.amount)}
          nella categoria <strong>${escH(data.cost_entry.category)}</strong>
          per il fornitore <strong>${escH(data.cost_entry.supplier)}</strong>
        </div>`;
      }
      html += `<table class="table table-sm table-bordered text-center">
        <thead style="background:#e2ae76;color:#041930;">
          <tr><th class="text-start ps-2">Ingrediente</th><th>Azione</th><th>Prezzo / kg</th></tr>
        </thead><tbody>`;
      (data.details || []).forEach(d => {
        const badge   = d.action==='created'
          ? '<span class="badge bg-success">Creato</span>'
          : '<span class="badge bg-warning text-dark">Aggiornato</span>';
        const matched = d.matched_to ? `<br><small class="text-muted">→ ${escH(d.matched_to)}</small>` : '';
        html += `<tr>
          <td class="text-start ps-2">${escH(d.name)}${matched}</td>
          <td>${badge}</td>
          <td>€${fmtP(d.price)}/kg</td>
        </tr>`;
      });
      html += '</tbody></table>';
    } else {
      html = `<div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        ${escH(data.message||'Errore sconosciuto.')}
      </div>`;
    }

    document.getElementById('resultModalBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('invoiceResultModal')).show();

  } catch (err) {
    alert('Errore durante il salvataggio: '+err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Conferma e Salva Ingredienti';
  }
}

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 5 — DYNAMIC ALIAS INPUTS (Manual Form)
═════════════════════════════════════════════════════════════════════════ */

function addAliasRow(value = '') {
  const wrapper = document.getElementById('aliasesWrapper');
  const div = document.createElement('div');
  div.className = 'alias-row input-group';
  div.innerHTML = `
    <input type="text" class="form-control alias-input"
           placeholder="es. White Sugar" value="${escH(value)}">
    <button type="button" class="btn btn-outline-danger btn-sm alias-remove-btn"
            onclick="removeAliasRow(this)" title="Rimuovi">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  wrapper.appendChild(div);
  div.querySelector('input').focus();
}

function removeAliasRow(btn) {
  const wrapper = document.getElementById('aliasesWrapper');
  const row = btn.closest('.alias-row');
  // Keep at least 1 row
  if (wrapper.querySelectorAll('.alias-row').length > 1) {
    row.remove();
  } else {
    row.querySelector('input').value = '';
  }
}

function serializeAliases() {
  const values = [...document.querySelectorAll('#aliasesWrapper .alias-input')]
    .map(i => i.value.trim())
    .filter(v => v !== '');
  document.getElementById('additionalNamesRaw').value = values.join(',');
}

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 6 — ALIAS MODAL
═════════════════════════════════════════════════════════════════════════ */

function openAliasModal(id, name, aliases) {
  document.getElementById('aliasIngIdHidden').value        = id;
  document.getElementById('aliasIngNameLabel').textContent = name;

  const wrapper = document.getElementById('modalAliasesWrapper');
  wrapper.innerHTML = '';

  const list = Array.isArray(aliases) && aliases.length ? aliases : [''];
  list.forEach(a => addModalAliasRow(a));

  new bootstrap.Modal(document.getElementById('aliasModal')).show();
}

function addModalAliasRow(value = '') {
  const wrapper = document.getElementById('modalAliasesWrapper');
  const div = document.createElement('div');
  div.className = 'alias-row input-group';
  div.innerHTML = `
    <input type="text" class="form-control modal-alias-input"
           placeholder="es. White Sugar" value="${escH(value)}">
    <button type="button" class="btn btn-outline-danger btn-sm alias-remove-btn"
            onclick="removeModalAliasRow(this)" title="Rimuovi">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  wrapper.appendChild(div);
  if (!value) div.querySelector('input').focus();
}

function removeModalAliasRow(btn) {
  const wrapper = document.getElementById('modalAliasesWrapper');
  const row = btn.closest('.alias-row');
  if (wrapper.querySelectorAll('.alias-row').length > 1) {
    row.remove();
  } else {
    row.querySelector('input').value = '';
  }
}

async function saveAliasesAjax() {
  const id = document.getElementById('aliasIngIdHidden').value;

  const raw = [...document.querySelectorAll('#modalAliasesWrapper .modal-alias-input')]
    .map(i => i.value.trim())
    .filter(v => v !== '')
    .join(',');

  try {
    const resp = await fetch(`/ingredients/${id}/aliases`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN':  csrfToken(),
        'Accept':        'application/json',
      },
      body: JSON.stringify({ additional_names: raw }),
    });
    const data = await resp.json();
    if (data.success) {
      bootstrap.Modal.getInstance(document.getElementById('aliasModal'))?.hide();
      location.reload();
    } else {
      alert('Errore nel salvataggio alias.');
    }
  } catch (err) {
    alert('Errore: '+err.message);
  }
}

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 7 — CLIENT-SIDE MATCHING
═════════════════════════════════════════════════════════════════════════ */

function matchExisting(name) {
  const needle = normName(name);
  if (!needle) return null;
  return EXISTING_INGS.find(ing => {
    if (normName(ing.name) === needle) return true;
    return (ing.additional_names||[]).some(a => normName(a) === needle);
  }) || null;
}

function normName(s) {
  return String(s).toLowerCase().trim()
    .replace(/\s+/g,' ')
    .replace(/[^\p{L}\p{N}\s]/gu,'');
}

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 8 — DATATABLES INIT
═════════════════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {

  const ingForm = document.getElementById('ingredientForm');
  if (ingForm) {
    ingForm.addEventListener('submit', () => serializeAliases());
  }

  document.querySelectorAll('[data-bs-toggle="tooltip"]')
    .forEach(el => new bootstrap.Tooltip(el));

  document.querySelectorAll('.needs-validation').forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    }, false);
  });

  if (window.$ && $.fn.DataTable) {
    $.fn.dataTable.ext.errMode = 'none';
    const SK_COL = 'ingredients_sort_col';
    const SK_DIR = 'ingredients_sort_dir';

    const table = $('#ingredientsTable').DataTable({
      responsive:  true,
      paging:      true,
      ordering:    true,
      orderMulti:  false,
      pageLength:  $('#ingredientsTable').data('page-length') || 25,
      order:       [[0,'asc']],
      columnDefs:  [{ orderable:false, targets:[2,4] }],
      language: {
        emptyTable:'Nessun ingrediente trovato.',
        search:'_INPUT_', searchPlaceholder:'Cerca ingredienti...',
        lengthMenu:'Mostra _MENU_ elementi',
        zeroRecords:'Nessun risultato',
        info:'Mostra _START_ a _END_ di _TOTAL_ elementi',
        infoEmpty:'Nessun ingrediente disponibile',
        paginate:{ first:'',last:'',next:'→',previous:'←' },
      },
    });

    try {
      const sc = sessionStorage.getItem(SK_COL);
      const sd = sessionStorage.getItem(SK_DIR);
      if (sc !== null && sd) table.order([parseInt(sc,10), sd]).draw();
    } catch(e){}

    function updateSortIndicators() {
      $('#ingredientsTable thead th.sortable').removeAttr('data-sort-dir').find('.sort-indicator').text('');
      const ord = table.order();
      if (!ord.length) return;
      const th = $('#ingredientsTable thead th').eq(ord[0][0]);
      if (!th.hasClass('sortable')) return;
      th.attr('data-sort-dir', ord[0][1]);
      th.find('.sort-indicator').text(ord[0][1]==='asc' ? '▲' : '▼');
    }
    updateSortIndicators();

    $('#ingredientsTable thead').on('click','th.sortable', function () {
      const idx = $(this).index();
      if (table.settings()[0].aoColumns[idx].bSortable === false) return;
      const cur    = table.order();
      const curCol = cur.length ? cur[0][0] : null;
      const curDir = cur.length ? cur[0][1] : 'asc';
      const newDir = (curCol === idx && curDir === 'asc') ? 'desc' : 'asc';
      table.order([idx, newDir]).draw();
      updateSortIndicators();
      try {
        const o = table.order();
        sessionStorage.setItem(SK_COL, o[0][0]);
        sessionStorage.setItem(SK_DIR, o[0][1]);
      } catch(e){}
    });

    $('#ingredientsTable thead').on('mousedown','th', e => { if (e.shiftKey) e.preventDefault(); });
  }
});

/* ═════════════════════════════════════════════════════════════════════════
   SECTION 9 — UTILITIES
═════════════════════════════════════════════════════════════════════════ */

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}
function escH(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function fmtP(v) { return parseFloat(v||0).toFixed(4); }
</script>
@endsection