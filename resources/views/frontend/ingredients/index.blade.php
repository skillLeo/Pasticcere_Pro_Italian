@extends('frontend.layouts.app')

@section('title', 'Ingredienti')

@section('content')

{{-- ══════════════════════════════════════════════════════════
     PAGE WRAPPER
══════════════════════════════════════════════════════════ --}}
<div class="ing-page">

  {{-- ── PAGE HEADER ─────────────────────────────────────── --}}
  <div class="ing-page-header">
    <div>
      <h1 class="ing-page-title">
        <span class="ing-page-icon"><i class="bi bi-basket3-fill"></i></span>
        Ingredienti
      </h1>
      <p class="ing-page-subtitle">Gestisci il tuo catalogo ingredienti e i prezzi aggiornati</p>
    </div>
    <div class="ing-header-stats">
      <div class="ing-stat-chip">
        <i class="bi bi-collection"></i>
        <strong>{{ $ingredients->count() }}</strong> ingredienti
      </div>
    </div>
  </div>


  {{-- ══ 1. UPLOAD INVOICE CARD ══════════════════════════════ --}}
  <div class="ing-card ing-card--upload mb-4">
    <div class="ing-card-header">
      <div class="ing-card-header-left">
        <span class="ing-card-header-icon"><i class="bi bi-cloud-arrow-up-fill"></i></span>
        <div>
          <h5 class="ing-card-title">Carica Fattura — Estrazione AI Automatica</h5>
          <p class="ing-card-subtitle mb-0">Analisi OCR Google Vision + GPT-4o</p>
        </div>
      </div>
      <span class="ing-badge-ai">
        <i class="bi bi-stars me-1"></i>AI Powered
      </span>
    </div>
    <div class="ing-card-body">
      <div id="dropZone" class="ing-dropzone"
           onclick="document.getElementById('invoiceFileInput').click()"
           ondragover="event.preventDefault();this.classList.add('ing-dropzone--active');"
           ondragleave="this.classList.remove('ing-dropzone--active');"
           ondrop="handleInvDrop(event)">

        <div id="dzContent" class="ing-dropzone-content">
          <div class="ing-dropzone-icon">
            <i class="bi bi-file-earmark-arrow-up"></i>
          </div>
          <p class="ing-dropzone-title">Clicca o trascina la fattura qui</p>
          <p class="ing-dropzone-hint">JPG, PNG, WEBP, PDF — max 20 MB</p>
          <div class="ing-dropzone-formats">
            <span><i class="bi bi-file-image me-1"></i>Immagini</span>
            <span><i class="bi bi-file-pdf me-1"></i>PDF</span>
            <span><i class="bi bi-lightning-charge me-1"></i>Risultati in secondi</span>
          </div>
        </div>

        <div id="dzLoading" class="ing-dropzone-loading d-none">
          <div class="ing-spinner">
            <div class="ing-spinner-ring"></div>
            <i class="bi bi-cpu-fill ing-spinner-icon"></i>
          </div>
          <p class="ing-dropzone-title mt-3">Elaborazione in corso...</p>
          <p class="ing-dropzone-hint" id="dzLoadingStep">Analisi OCR con Google Vision...</p>
        </div>
      </div>

      <input type="file" id="invoiceFileInput" accept=".jpg,.jpeg,.png,.webp,.pdf"
             class="d-none" onchange="handleInvFileSelect(this)">

      <div id="invUploadErr" class="ing-alert ing-alert--danger mt-3 d-none" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <span id="invUploadErrMsg"></span>
      </div>
    </div>
  </div>


  {{-- ══ 2. ADD / EDIT INGREDIENT FORM ══════════════════════ --}}
  <div class="ing-card mb-5">
    <div class="ing-card-header">
      <div class="ing-card-header-left">
        <span class="ing-card-header-icon">
          <i class="bi bi-{{ isset($ingredient) ? 'pencil-square' : 'plus-circle-fill' }}"></i>
        </span>
        <div>
          <h5 class="ing-card-title">
            {{ isset($ingredient) ? 'Modifica Ingrediente' : 'Aggiungi Ingrediente' }}
          </h5>
          <p class="ing-card-subtitle mb-0">
            {{ isset($ingredient) ? 'Modifica i dati dell\'ingrediente selezionato' : 'Inserisci un nuovo ingrediente nel catalogo' }}
          </p>
        </div>
      </div>
    </div>
    <div class="ing-card-body">
      <form
        action="{{ isset($ingredient) ? route('ingredients.update', $ingredient) : route('ingredients.store') }}"
        method="POST"
        class="needs-validation"
        id="ingredientForm"
        novalidate
      >
        @csrf
        @if(isset($ingredient)) @method('PUT') @endif

        <div class="ing-form-grid">

          {{-- Name --}}
          <div class="ing-form-group">
            <label for="ingredientName" class="ing-label">
              <i class="bi bi-tag me-1"></i>Nome Principale <span class="ing-required">*</span>
            </label>
            <input type="text" id="ingredientName" name="ingredient_name"
                   class="ing-input @error('ingredient_name') is-invalid @enderror"
                   placeholder="es. Farina 00"
                   value="{{ old('ingredient_name', $ingredient->ingredient_name ?? '') }}" required>
            <div class="ing-feedback-invalid">
              @error('ingredient_name'){{ $message }}@else Inserisci un nome. @enderror
            </div>
          </div>

          {{-- Price --}}
          <div class="ing-form-group">
            <label for="pricePerKg" class="ing-label">
              <i class="bi bi-currency-euro me-1"></i>Prezzo al kg <span class="ing-required">*</span>
            </label>
            <div class="ing-input-group">
              <span class="ing-input-addon">€</span>
              <input type="number" id="pricePerKg" name="price_per_kg"
                     class="ing-input ing-input--mid @error('price_per_kg') is-invalid @enderror"
                     step="0.0001" min="0.0001" placeholder="0.0000"
                     value="{{ old('price_per_kg', isset($ingredient) ? number_format($ingredient->price_per_kg, 4, '.', '') : '') }}" required>
              <span class="ing-input-addon">/kg</span>
              <div class="ing-feedback-invalid">
                @error('price_per_kg'){{ $message }}@else Inserisci un prezzo valido. @enderror
              </div>
            </div>
          </div>

          {{-- Aliases --}}
          <div class="ing-form-group ing-form-group--full">
            <label class="ing-label">
              <i class="bi bi-tags me-1"></i>Nomi Alternativi (alias)
              <span class="ing-tooltip-trigger" data-bs-toggle="tooltip"
                    title="Ogni alias viene usato per il matching automatico delle fatture.">
                <i class="bi bi-question-circle-fill"></i>
              </span>
            </label>
            <input type="hidden" name="additional_names_raw" id="additionalNamesRaw">

            @php
              $existingAliases = isset($ingredient) && is_array($ingredient->additional_names ?? null) && count($ingredient->additional_names)
                  ? $ingredient->additional_names
                  : (old('additional_names_raw') ? array_filter(array_map('trim', explode(',', old('additional_names_raw')))) : []);
            @endphp

            <div id="aliasesWrapper" class="ing-aliases-wrapper">
              @forelse($existingAliases as $alias)
                <div class="ing-alias-row">
                  <i class="bi bi-arrow-right-short ing-alias-icon"></i>
                  <input type="text" class="ing-input ing-input--sm alias-input"
                         placeholder="es. White Sugar"
                         value="{{ trim($alias) }}">
                  <button type="button" class="ing-alias-remove" onclick="removeAliasRow(this)" title="Rimuovi">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>
              @empty
                <div class="ing-alias-row">
                  <i class="bi bi-arrow-right-short ing-alias-icon"></i>
                  <input type="text" class="ing-input ing-input--sm alias-input"
                         placeholder="es. White Sugar" value="">
                  <button type="button" class="ing-alias-remove" onclick="removeAliasRow(this)" title="Rimuovi">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>
              @endforelse
            </div>

            <button type="button" class="ing-btn-add-alias mt-2" onclick="addAliasRow()">
              <i class="bi bi-plus-circle me-1"></i>Aggiungi alias
            </button>
            <div class="ing-form-hint">Uno per riga — opzionale. Usato per il matching automatico fatture.</div>
          </div>
        </div>

        <div class="ing-form-actions">
          @if(isset($ingredient))
            <a href="{{ route('ingredients.index') }}" class="ing-btn ing-btn--secondary">
              <i class="bi bi-x-lg me-2"></i>Annulla
            </a>
          @endif
          <button type="submit" class="ing-btn ing-btn--primary">
            <i class="bi bi-save2 me-2"></i>
            {{ isset($ingredient) ? 'Aggiorna Ingrediente' : 'Salva Ingrediente' }}
          </button>
        </div>
      </form>
    </div>
  </div>


  {{-- ══ 3. INGREDIENTS TABLE ════════════════════════════════ --}}
  <div class="ing-card">
    <div class="ing-card-header">
      <div class="ing-card-header-left">
        <span class="ing-card-header-icon"><i class="bi bi-grid-3x3-gap-fill"></i></span>
        <div>
          <h5 class="ing-card-title">Vetrina Ingredienti</h5>
          <p class="ing-card-subtitle mb-0">{{ $ingredients->count() }} ingredienti nel catalogo</p>
        </div>
      </div>
    </div>
    <div class="ing-card-body p-0">
      <div class="table-responsive">
        <table id="ingredientsTable"
               class="ing-table"
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
              <td class="fw-semibold text-start ps-4">
                <div class="ing-table-name-cell">
                  <span class="ing-table-avatar">{{ strtoupper(substr($ing->ingredient_name, 0, 1)) }}</span>
                  {{ $ing->ingredient_name }}
                </div>
              </td>
              <td data-order="{{ $ing->price_per_kg }}">
                <span class="ing-price-badge">€{{ number_format((float)$ing->price_per_kg, 4, '.', ',') }}<span class="ing-price-unit">/kg</span></span>
              </td>
              <td>
                @forelse($ing->additional_names ?? [] as $alias)
                  <span class="ing-alias-badge">{{ $alias }}</span>
                @empty
                  <span class="ing-table-empty">—</span>
                @endforelse
              </td>
              <td data-order="{{ $ing->updated_at->format('Y-m-d H:i') }}">
                <span class="ing-date-cell">
                  <i class="bi bi-clock me-1"></i>
                  {{ $ing->updated_at->format('d/m/Y H:i') }}
                </span>
              </td>
              <td>
                <div class="ing-action-group">
                  <a href="{{ route('ingredients.edit', $ing) }}"
                     class="ing-action-btn ing-action-btn--edit" title="Modifica">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="{{ route('ingredients.show', $ing) }}"
                     class="ing-action-btn ing-action-btn--view" title="Visualizza">
                    <i class="bi bi-eye"></i>
                  </a>
                  <button type="button" class="ing-action-btn ing-action-btn--tags" title="Gestisci Alias"
                          onclick="openAliasModal({{ $ing->id }}, @js($ing->ingredient_name), @js($ing->additional_names ?? []))">
                    <i class="bi bi-tags"></i>
                  </button>
                  <form action="{{ route('ingredients.destroy', $ing) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Eliminare «{{ $ing->ingredient_name }}»?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="ing-action-btn ing-action-btn--delete" title="Elimina">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>{{-- /ing-page --}}


{{-- ══════════════════════════════════════════════════════════
     MODAL 1 — Invoice Preview & Edit
══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="invoicePreviewModal" tabindex="-1"
     aria-labelledby="invPreviewTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable ing-modal-dialog">
    <div class="modal-content ing-modal-content">

      <div class="ing-modal-header">
        <div class="ing-modal-header-left">
          <span class="ing-modal-icon"><i class="bi bi-file-earmark-check"></i></span>
          <div>
            <h5 class="ing-modal-title" id="invPreviewTitle">Anteprima Fattura</h5>
            <p class="ing-modal-subtitle">Verifica e correggi prima di salvare</p>
          </div>
        </div>
        <button type="button" class="ing-modal-close" data-bs-dismiss="modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body p-4">

        {{-- Invoice meta --}}
        <div class="ing-invoice-meta">
          <div class="ing-invoice-meta-field">
            <label class="ing-label"><i class="bi bi-building me-1"></i>Fornitore</label>
            <input type="text" id="prevSupplier" class="ing-input" placeholder="Nome fornitore">
          </div>
          <div class="ing-invoice-meta-field">
            <label class="ing-label"><i class="bi bi-calendar3 me-1"></i>Data Fattura</label>
            <input type="date" id="prevDate" class="ing-input">
          </div>
          <div class="ing-invoice-meta-field">
            <label class="ing-label"><i class="bi bi-hash me-1"></i>Codice Fattura</label>
            <input type="text" id="prevInvoiceCode" class="ing-input" placeholder="FAC-2025-001234">
          </div>
          <div class="ing-match-summary-box">
            <p class="mb-0" id="prevMatchSummary">
              <i class="bi bi-info-circle me-1"></i>Caricamento...
            </p>
          </div>
        </div>

        {{-- Items table --}}
        <div class="table-responsive mt-3">
          <table class="ing-prev-table" id="prevItemsTable">
            <thead>
              <tr>
                <th style="width:27%;">Nome Ingrediente</th>
                <th style="width:22%;">Prezzo / kg (€)</th>
                <th style="width:15%;">Totale Riga (€)</th>
                <th style="width:24%;">Valore Originale Fattura</th>
                <th style="width:7%;">Stato</th>
                <th style="width:5%;">✕</th>
              </tr>
            </thead>
            <tbody id="prevItemsBody"></tbody>
            <tfoot>
              <tr class="ing-prev-table-foot">
                <td colspan="2" class="text-end fw-bold pe-3">Totale Fattura Ingredienti:</td>
                <td class="fw-bold" id="invGrandTotal" style="color:#16a34a;">€0.0000</td>
                <td colspan="3" class="text-muted small fst-italic">
                  <i class="bi bi-receipt me-1"></i>Verrà registrato nei Costi
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-3 d-flex gap-2 align-items-center flex-wrap">
          <button type="button" class="ing-btn ing-btn--ghost ing-btn--sm" onclick="addManualPrevRow()">
            <i class="bi bi-plus-circle me-1"></i>Aggiungi Riga Manuale
          </button>
          <span class="text-muted small">
            <i class="bi bi-lightbulb me-1" style="color:#e2ae76;"></i>
            Modifica direttamente nelle celle prima di confermare.
          </span>
        </div>

        {{-- Raw OCR --}}
        <div class="mt-3">
          <button class="ing-btn ing-btn--ghost ing-btn--sm" type="button"
                  data-bs-toggle="collapse" data-bs-target="#rawOcrCollapse">
            <i class="bi bi-code-square me-1"></i>Mostra testo OCR grezzo
          </button>
          <div class="collapse mt-2" id="rawOcrCollapse">
            <pre id="rawOcrContent" class="ing-ocr-raw"></pre>
          </div>
        </div>
      </div>

      <div class="ing-modal-footer">
        <button type="button" class="ing-btn ing-btn--secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i>Annulla
        </button>
        <button type="button" id="confirmInvBtn" onclick="submitInvoiceData()"
                class="ing-btn ing-btn--confirm">
          <i class="bi bi-check2-circle me-2"></i>Conferma e Salva Ingredienti
        </button>
      </div>
    </div>
  </div>
</div>


{{-- ══════════════════════════════════════════════════════════
     MODAL 2 — Processing Result
══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="invoiceResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog ing-modal-dialog">
    <div class="modal-content ing-modal-content">
      <div class="ing-modal-header">
        <div class="ing-modal-header-left">
          <span class="ing-modal-icon"><i class="bi bi-check-circle-fill"></i></span>
          <div>
            <h5 class="ing-modal-title">Risultato Elaborazione</h5>
          </div>
        </div>
        <button type="button" class="ing-modal-close" data-bs-dismiss="modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal-body p-4" id="resultModalBody"></div>
      <div class="ing-modal-footer">
        <button type="button" class="ing-btn ing-btn--primary" onclick="location.reload()">
          <i class="bi bi-arrow-clockwise me-2"></i>Aggiorna Pagina
        </button>
      </div>
    </div>
  </div>
</div>


{{-- ══════════════════════════════════════════════════════════
     MODAL 3 — Manage Aliases
══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="aliasModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog ing-modal-dialog">
    <div class="modal-content ing-modal-content">
      <div class="ing-modal-header">
        <div class="ing-modal-header-left">
          <span class="ing-modal-icon"><i class="bi bi-tags-fill"></i></span>
          <div>
            <h5 class="ing-modal-title">Gestisci Nomi Alternativi</h5>
          </div>
        </div>
        <button type="button" class="ing-modal-close" data-bs-dismiss="modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="modal-body p-4">
        <p class="fw-bold mb-3" id="aliasIngNameLabel" style="color:#041930;font-size:1.1rem;"></p>
        <input type="hidden" id="aliasIngIdHidden">

        <label class="ing-label mb-2">
          Aggiungi tutti i nomi con cui questo ingrediente può apparire sulle fatture:
        </label>

        <div id="modalAliasesWrapper" class="ing-aliases-wrapper mb-2"></div>

        <button type="button" class="ing-btn-add-alias" onclick="addModalAliasRow()">
          <i class="bi bi-plus-circle me-1"></i>Aggiungi alias
        </button>

        <div class="ing-info-box mt-3">
          <i class="bi bi-info-circle-fill me-2" style="color:#e2ae76;"></i>
          <div>
            <strong>Come funziona:</strong> Quando carichi una fattura e un fornitore scrive
            il nome diversamente (es. <em>"White Sugar"</em> invece di <em>"Zucchero"</em>),
            il sistema lo riconoscerà automaticamente.
          </div>
        </div>
      </div>
      <div class="ing-modal-footer">
        <button type="button" class="ing-btn ing-btn--secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i>Chiudi
        </button>
        <button type="button" class="ing-btn ing-btn--primary" onclick="saveAliasesAjax()">
          <i class="bi bi-save2 me-1"></i>Salva Alias
        </button>
      </div>
    </div>
  </div>
</div>

@endsection


{{-- ══ STYLES ══════════════════════════════════════════════ --}}
<style>
/* ── Variables ─────────────────────────────────────────── */
:root {
  --gold:       #e2ae76;
  --gold-light: #f5e6cf;
  --gold-dark:  #c9954d;
  --navy:       #041930;
  --navy-mid:   #093060;
  --navy-light: #0d4a8a;
  --bg:         #f7f8fc;
  --surface:    #ffffff;
  --border:     #e4e8f0;
  --text:       #1a2332;
  --text-muted: #6b7a99;
  --success:    #16a34a;
  --danger:     #dc2626;
  --warning:    #ca8a04;
  --shadow-sm:  0 1px 3px rgba(4,25,48,.07), 0 1px 2px rgba(4,25,48,.04);
  --shadow-md:  0 4px 16px rgba(4,25,48,.10), 0 2px 6px rgba(4,25,48,.06);
  --shadow-lg:  0 10px 40px rgba(4,25,48,.14), 0 4px 12px rgba(4,25,48,.08);
  --radius:     12px;
  --radius-sm:  8px;
  --radius-lg:  16px;
}

/* ── Page Layout ───────────────────────────────────────── */
.ing-page {
  max-width: 1280px;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem;
}

.ing-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1.75rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.ing-page-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--navy);
  margin-bottom: .25rem;
  display: flex;
  align-items: center;
  gap: .6rem;
  letter-spacing: -.02em;
}

.ing-page-icon {
  width: 44px; height: 44px;
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  color: var(--gold);
  font-size: 1.2rem;
  flex-shrink: 0;
}

.ing-page-subtitle {
  color: var(--text-muted);
  margin: 0;
  font-size: .9rem;
}

.ing-header-stats { display: flex; gap: .75rem; align-items: center; }

.ing-stat-chip {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: .4rem 1rem;
  font-size: .875rem;
  color: var(--navy);
  display: flex; align-items: center; gap: .5rem;
  box-shadow: var(--shadow-sm);
}
.ing-stat-chip i { color: var(--gold); }

/* ── Cards ─────────────────────────────────────────────── */
.ing-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.ing-card--upload {
  border-top: 3px solid var(--gold);
}

.ing-card-header {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
  padding: 1.1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .75rem;
}

.ing-card-header-left {
  display: flex;
  align-items: center;
  gap: .875rem;
}

.ing-card-header-icon {
  width: 40px; height: 40px;
  background: rgba(226,174,118,.18);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--gold);
  font-size: 1.2rem;
  flex-shrink: 0;
}

.ing-card-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--gold);
  margin: 0;
}

.ing-card-subtitle {
  font-size: .8rem;
  color: rgba(226,174,118,.65);
}

.ing-card-body {
  padding: 1.5rem;
}

.ing-badge-ai {
  background: rgba(226,174,118,.15);
  border: 1px solid rgba(226,174,118,.3);
  color: var(--gold);
  font-size: .75rem;
  font-weight: 600;
  padding: .35rem .8rem;
  border-radius: 20px;
  white-space: nowrap;
}

/* ── Drop Zone ─────────────────────────────────────────── */
.ing-dropzone {
  border: 2px dashed var(--gold);
  border-radius: var(--radius);
  background: #fffdf9;
  cursor: pointer;
  padding: 2.5rem 2rem;
  text-align: center;
  transition: all .2s ease;
  position: relative;
  overflow: hidden;
}

.ing-dropzone::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at top, rgba(226,174,118,.08) 0%, transparent 70%);
  pointer-events: none;
}

.ing-dropzone--active,
.ing-dropzone:hover {
  background: #fdf3e3;
  border-color: var(--gold-dark);
  box-shadow: 0 0 0 4px rgba(226,174,118,.12);
}

.ing-dropzone-content { position: relative; }
.ing-dropzone-loading  { position: relative; }

.ing-dropzone-icon {
  width: 72px; height: 72px;
  background: linear-gradient(135deg, rgba(226,174,118,.15), rgba(226,174,118,.05));
  border-radius: 50%;
  margin: 0 auto .875rem;
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem;
  color: var(--gold-dark);
}

.ing-dropzone-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--navy);
  margin-bottom: .35rem;
}

.ing-dropzone-hint {
  font-size: .85rem;
  color: var(--text-muted);
  margin-bottom: .875rem;
}

.ing-dropzone-formats {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}

.ing-dropzone-formats span {
  font-size: .78rem;
  color: var(--text-muted);
  background: white;
  border: 1px solid var(--border);
  padding: .25rem .65rem;
  border-radius: 20px;
}

/* ── Spinner ───────────────────────────────────────────── */
.ing-spinner {
  width: 64px; height: 64px;
  margin: 0 auto;
  position: relative;
  display: flex; align-items: center; justify-content: center;
}

.ing-spinner-ring {
  position: absolute;
  inset: 0;
  border: 3px solid rgba(226,174,118,.2);
  border-top-color: var(--gold);
  border-radius: 50%;
  animation: ing-spin 1s linear infinite;
}

.ing-spinner-icon {
  font-size: 1.5rem;
  color: var(--gold);
}

@keyframes ing-spin { to { transform: rotate(360deg); } }

/* ── Alert ─────────────────────────────────────────────── */
.ing-alert {
  padding: .875rem 1rem;
  border-radius: var(--radius-sm);
  font-size: .9rem;
  display: flex;
  align-items: center;
}

.ing-alert--danger {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

/* ── Form ──────────────────────────────────────────────── */
.ing-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.ing-form-group { display: flex; flex-direction: column; }
.ing-form-group--full { grid-column: 1 / -1; }

.ing-label {
  font-size: .85rem;
  font-weight: 600;
  color: var(--navy);
  margin-bottom: .4rem;
  display: flex;
  align-items: center;
  gap: .35rem;
}

.ing-required { color: var(--danger); font-size: .9em; }

.ing-tooltip-trigger {
  color: var(--text-muted);
  cursor: help;
  font-size: .85em;
}

.ing-input {
  height: 44px;
  padding: .5rem .875rem;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: .9rem;
  color: var(--text);
  background: var(--surface);
  transition: border-color .15s, box-shadow .15s;
  width: 100%;
  outline: none;
}

.ing-input:focus {
  border-color: var(--gold);
  box-shadow: 0 0 0 3px rgba(226,174,118,.18);
}

.ing-input.is-invalid {
  border-color: var(--danger);
}

.ing-input--sm { height: 38px; font-size: .875rem; }

.ing-input--mid { border-left: none; border-right: none; border-radius: 0; }

.ing-input-group {
  display: flex;
  align-items: stretch;
  position: relative;
}

.ing-input-group .ing-feedback-invalid {
  position: absolute;
  top: 100%;
  left: 0;
}

.ing-input-addon {
  height: 44px;
  padding: 0 .875rem;
  background: #f8f9fb;
  border: 1.5px solid var(--border);
  color: var(--text-muted);
  font-size: .875rem;
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.ing-input-addon:first-child {
  border-radius: var(--radius-sm) 0 0 var(--radius-sm);
  border-right: none;
}

.ing-input-addon:last-child {
  border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
  border-left: none;
}

.ing-input-group:focus-within .ing-input-addon {
  border-color: var(--gold);
}

.ing-feedback-invalid {
  font-size: .8rem;
  color: var(--danger);
  margin-top: .25rem;
  display: none;
}

.was-validated .ing-input:invalid + .ing-feedback-invalid,
.ing-input.is-invalid ~ .ing-feedback-invalid { display: block; }

.ing-form-hint {
  font-size: .78rem;
  color: var(--text-muted);
  margin-top: .35rem;
}

.ing-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: .75rem;
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border);
}

/* ── Aliases ───────────────────────────────────────────── */
.ing-aliases-wrapper {
  display: flex;
  flex-direction: column;
  gap: .5rem;
  min-height: 44px;
}

.ing-alias-row {
  display: flex;
  align-items: center;
  gap: .5rem;
}

.ing-alias-icon {
  color: var(--gold);
  font-size: 1.1rem;
  flex-shrink: 0;
}

.ing-alias-remove {
  flex-shrink: 0;
  width: 32px; height: 32px;
  border-radius: 6px;
  border: 1.5px solid #fca5a5;
  background: #fff5f5;
  color: #ef4444;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all .15s;
  font-size: .75rem;
}

.ing-alias-remove:hover {
  background: #fee2e2;
  border-color: #ef4444;
}

.ing-btn-add-alias {
  height: 34px;
  padding: 0 .875rem;
  border: 1.5px dashed var(--gold);
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--gold-dark);
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  transition: all .15s;
}

.ing-btn-add-alias:hover {
  background: var(--gold-light);
  border-style: solid;
}

/* ── Buttons ───────────────────────────────────────────── */
.ing-btn {
  height: 44px;
  padding: 0 1.375rem;
  border-radius: var(--radius-sm);
  font-size: .9rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid transparent;
  transition: all .15s;
  text-decoration: none;
  white-space: nowrap;
}

.ing-btn--primary {
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  color: var(--gold);
  border-color: transparent;
}
.ing-btn--primary:hover {
  background: linear-gradient(135deg, var(--navy-mid), var(--navy-light));
  color: var(--gold);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(4,25,48,.3);
}

.ing-btn--secondary {
  background: transparent;
  color: var(--text-muted);
  border-color: var(--border);
}
.ing-btn--secondary:hover {
  background: #f1f3f7;
  color: var(--text);
  border-color: #c8cdd8;
}

.ing-btn--ghost {
  background: transparent;
  color: var(--text-muted);
  border-color: var(--border);
  font-size: .82rem;
}
.ing-btn--ghost:hover { background: #f1f3f7; color: var(--text); }

.ing-btn--sm { height: 34px; padding: 0 .875rem; font-size: .82rem; }

.ing-btn--confirm {
  background: linear-gradient(135deg, #14532d, #166534);
  color: #bbf7d0;
  border-color: transparent;
}
.ing-btn--confirm:hover {
  background: linear-gradient(135deg, #166534, #15803d);
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(21,128,61,.3);
}
.ing-btn--confirm:disabled {
  opacity: .6;
  cursor: not-allowed;
  transform: none;
}

/* ── Table ─────────────────────────────────────────────── */
.ing-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: .875rem;
}

.ing-table thead tr th {
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  color: var(--gold);
  padding: .875rem 1rem;
  font-weight: 700;
  font-size: .8rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  text-align: center;
  border: none;
  white-space: nowrap;
  cursor: default;
}

.ing-table thead th.sortable {
  cursor: pointer;
  user-select: none;
  position: relative;
}

.ing-table thead th.sortable:hover {
  background: linear-gradient(135deg, var(--navy-mid), var(--navy-light));
}

.sort-indicator {
  display: inline-block;
  width: 14px;
  font-size: .65rem;
  opacity: 0;
  transition: opacity .15s;
}

th[data-sort-dir] .sort-indicator { opacity: 1; }

/* Override DataTables default sort icons */
table.dataTable thead .sorting:after,
table.dataTable thead .sorting_asc:after,
table.dataTable thead .sorting_desc:after,
table.dataTable thead .sorting:before,
table.dataTable thead .sorting_asc:before,
table.dataTable thead .sorting_desc:before { content: '' !important; }

.ing-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .1s;
}

.ing-table tbody tr:last-child { border-bottom: none; }

.ing-table tbody tr:hover {
  background: #fafbfe;
}

.ing-table tbody td {
  padding: .875rem 1rem;
  vertical-align: middle;
  text-align: center;
  color: var(--text);
}

.ing-table-name-cell {
  display: flex;
  align-items: center;
  gap: .65rem;
  text-align: left;
}

.ing-table-avatar {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  color: var(--gold);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800;
  font-size: .8rem;
  flex-shrink: 0;
}

.ing-price-badge {
  display: inline-flex;
  align-items: baseline;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: var(--success);
  padding: .3rem .7rem;
  border-radius: 8px;
  font-weight: 700;
  font-size: .9rem;
  font-variant-numeric: tabular-nums;
  font-feature-settings: "tnum";
  letter-spacing: -.01em;
}

.ing-price-unit {
  font-size: .7rem;
  font-weight: 500;
  margin-left: .2rem;
  opacity: .7;
}

.ing-alias-badge {
  display: inline-block;
  background: var(--gold-light);
  color: var(--navy);
  border: 1px solid rgba(226,174,118,.4);
  font-size: .75rem;
  font-weight: 600;
  padding: .2rem .6rem;
  border-radius: 6px;
  margin: .15rem .15rem .15rem 0;
}

.ing-table-empty { color: #cbd5e1; font-size: 1.1rem; }

.ing-date-cell {
  font-size: .8rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .3rem;
}

.ing-action-group {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .3rem;
}

.ing-action-btn {
  width: 32px; height: 32px;
  border-radius: 7px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: .8rem;
  cursor: pointer;
  transition: all .15s;
  border: 1.5px solid transparent;
  background: transparent;
  text-decoration: none;
}

.ing-action-btn--edit {
  border-color: rgba(226,174,118,.5);
  color: var(--gold-dark);
}
.ing-action-btn--edit:hover { background: var(--gold-light); color: var(--gold-dark); }

.ing-action-btn--view {
  border-color: rgba(4,25,48,.2);
  color: var(--navy);
}
.ing-action-btn--view:hover { background: #e8edf5; color: var(--navy); }

.ing-action-btn--tags {
  border-color: rgba(111,66,193,.3);
  color: #7c3aed;
}
.ing-action-btn--tags:hover { background: #f5f3ff; color: #6d28d9; }

.ing-action-btn--delete {
  border-color: rgba(220,38,38,.3);
  color: var(--danger);
}
.ing-action-btn--delete:hover { background: #fef2f2; color: #b91c1c; }

/* DataTables overrides */
.dataTables_wrapper .dataTables_filter input {
  height: 38px;
  padding: .4rem .875rem;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: .875rem;
  outline: none;
  transition: border-color .15s;
  margin-left: .5rem;
}
.dataTables_wrapper .dataTables_filter input:focus { border-color: var(--gold); }

.dataTables_wrapper .dataTables_length select {
  height: 38px;
  padding: .4rem 2rem .4rem .75rem;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: .875rem;
  appearance: none;
  background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='%23e2ae76' viewBox='0 0 16 16'%3e%3cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3e%3c/svg%3e") no-repeat right .75rem center / 14px;
  margin: 0 .5rem;
}

.dataTables_wrapper .dataTables_info { font-size: .82rem; color: var(--text-muted); }
.dataTables_wrapper .dataTables_paginate { display: flex; gap: .3rem; align-items: center; }
.dataTables_wrapper .dataTables_paginate .paginate_button {
  min-width: 34px; height: 34px;
  padding: 0 .5rem;
  display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid var(--border) !important;
  border-radius: 7px;
  font-size: .82rem;
  cursor: pointer;
  transition: all .15s;
  color: var(--text) !important;
  background: var(--surface) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background: var(--navy) !important;
  color: var(--gold) !important;
  border-color: var(--navy) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
  opacity: .4;
  cursor: not-allowed;
}

.dataTables_wrapper > .row { margin: 0; padding: 1rem 1.5rem; }
.dataTables_wrapper > .row:first-child { border-bottom: 1px solid var(--border); }
.dataTables_wrapper > .row:last-child { border-top: 1px solid var(--border); }

/* ── Modals ────────────────────────────────────────────── */
.ing-modal-dialog { --bs-modal-margin: 1.5rem; }

.ing-modal-content {
  border-radius: var(--radius-lg);
  overflow: hidden;
  border: none;
  box-shadow: var(--shadow-lg);
}

.ing-modal-header {
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  padding: 1.1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ing-modal-header-left {
  display: flex;
  align-items: center;
  gap: .875rem;
}

.ing-modal-icon {
  width: 38px; height: 38px;
  background: rgba(226,174,118,.18);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  color: var(--gold);
  font-size: 1.1rem;
}

.ing-modal-title {
  font-size: .975rem;
  font-weight: 700;
  color: var(--gold);
  margin: 0;
}

.ing-modal-subtitle {
  font-size: .78rem;
  color: rgba(226,174,118,.6);
  margin: 0;
}

.ing-modal-close {
  width: 34px; height: 34px;
  border-radius: 8px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.7);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all .15s;
  font-size: .8rem;
}
.ing-modal-close:hover { background: rgba(255,255,255,.16); color: white; }

.ing-modal-footer {
  background: #f8f9fb;
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: .75rem;
  border-top: 1px solid var(--border);
}

/* ── Invoice Meta ──────────────────────────────────────── */
.ing-invoice-meta {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr auto;
  gap: .875rem;
  align-items: end;
}

.ing-invoice-meta-field { display: flex; flex-direction: column; }

.ing-match-summary-box {
  background: #fffdf9;
  border: 1px solid rgba(226,174,118,.4);
  border-radius: var(--radius-sm);
  padding: .75rem .875rem;
  font-size: .82rem;
  color: var(--navy);
}

/* ── Preview Table ─────────────────────────────────────── */
.ing-prev-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .875rem;
}

.ing-prev-table thead tr th {
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  color: var(--gold);
  padding: .75rem .875rem;
  font-weight: 700;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  text-align: center;
  border: none;
}

.ing-prev-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .1s;
}

.ing-prev-table tbody tr:hover { background: #fafbfe; }

.ing-prev-table tbody td {
  padding: .625rem .875rem;
  vertical-align: middle;
  text-align: center;
}

.ing-prev-table-foot td {
  padding: .75rem .875rem;
  background: #f8f9fb;
  border-top: 2px solid var(--border);
  vertical-align: middle;
}

/* Preview table inputs */
.ing-prev-table .ing-input { height: 36px; font-size: .85rem; }
.ing-prev-table .ing-input-addon { height: 36px; font-size: .82rem; }
.ing-prev-table .ing-input--mid { height: 36px; }

.ing-orig-calc { font-size: .78rem; line-height: 1.5; }
.ing-orig-raw  { color: var(--text-muted); }
.ing-orig-formula { color: var(--success); font-weight: 600; margin-top: .15rem; }

/* ── Status badges ─────────────────────────────────────── */
.ing-badge-new {
  display: inline-flex; align-items: center; gap: .3rem;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  color: #15803d;
  font-size: .73rem; font-weight: 700;
  padding: .25rem .6rem;
  border-radius: 20px;
}

.ing-badge-update {
  display: inline-flex; align-items: center; gap: .3rem;
  background: #fefce8; border: 1px solid #fde68a;
  color: #92400e;
  font-size: .73rem; font-weight: 700;
  padding: .25rem .6rem;
  border-radius: 20px;
}

/* ── OCR Raw ───────────────────────────────────────────── */
.ing-ocr-raw {
  background: #1e2533;
  color: #a8b8d8;
  border-radius: var(--radius-sm);
  padding: 1rem;
  font-size: .75rem;
  max-height: 200px;
  overflow-y: auto;
  white-space: pre-wrap;
  line-height: 1.5;
}

/* ── Info Box ──────────────────────────────────────────── */
.ing-info-box {
  background: #fffdf9;
  border: 1px solid rgba(226,174,118,.4);
  border-radius: var(--radius-sm);
  padding: .875rem;
  font-size: .83rem;
  color: var(--navy);
  display: flex;
  gap: .5rem;
}

/* ── Responsive ────────────────────────────────────────── */
@media (max-width: 768px) {
  .ing-form-grid { grid-template-columns: 1fr; }
  .ing-form-group--full { grid-column: 1; }
  .ing-invoice-meta { grid-template-columns: 1fr 1fr; }
  .ing-match-summary-box { grid-column: 1 / -1; }
  .ing-page-header { flex-direction: column; }
}

@media (max-width: 576px) {
  .ing-invoice-meta { grid-template-columns: 1fr; }
}

/* ── Result Modal Table ────────────────────────────────── */
.ing-result-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .875rem;
}

.ing-result-table thead th {
  background: linear-gradient(135deg, var(--navy), var(--navy-mid));
  color: var(--gold);
  padding: .625rem .875rem;
  font-weight: 700;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.ing-result-table tbody tr { border-bottom: 1px solid var(--border); }
.ing-result-table tbody tr:last-child { border-bottom: none; }
.ing-result-table tbody td {
  padding: .625rem .875rem;
  vertical-align: middle;
}
</style>


{{-- ══ SCRIPTS ══════════════════════════════════════════════ --}}
@section('scripts')
<script>
'use strict';

const EXISTING_INGS = @json($ingredientsForJs);

/* ═══════════════════════════════════════════════════════════
   SECTION 1 — UPLOAD / DROP
═══════════════════════════════════════════════════════════ */

function handleInvDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.remove('ing-dropzone--active');
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
  if (!allowed.includes(file.type)) { showInvErr('Tipo file non supportato. Usa JPG, PNG, WEBP o PDF.'); return; }
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
  document.getElementById('invUploadErrMsg').textContent = m;
  el.classList.remove('d-none');
}
function hideInvErr() { document.getElementById('invUploadErr').classList.add('d-none'); }


/* ═══════════════════════════════════════════════════════════
   SECTION 2 — PREVIEW MODAL: POPULATE
═══════════════════════════════════════════════════════════ */

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


/* ═══════════════════════════════════════════════════════════
   SECTION 3 — PREVIEW MODAL: ROWS
═══════════════════════════════════════════════════════════ */

function appendPrevRow(item) {
  const tbody     = document.getElementById('prevItemsBody');
  const match     = matchExisting(item.name || '');
  // BUG FIX: always use parseSafeFloat so comma-decimal is handled correctly
  const pricePerKg = parseSafeFloat(item.price_per_kg || 0);
  const lineTotal  = parseSafeFloat(item.original_price || 0);

  let origHtml = '';
  const rawStr  = item.original_price_raw ? escH(item.original_price_raw) : fmtP(pricePerKg);
  const origUnit = escH(item.original_unit || 'kg');

  if (item.notes && item.notes !== '') {
    origHtml = `<div class="ing-orig-calc">
      <div class="ing-orig-raw">${rawStr} / ${origUnit}</div>
      <div class="ing-orig-formula">${escH(item.notes)}</div>
    </div>`;
  } else {
    origHtml = `<div class="ing-orig-calc"><div class="ing-orig-raw">${rawStr} / ${origUnit}</div></div>`;
  }

  const tr = document.createElement('tr');
  tr.dataset.lineTotal = lineTotal.toFixed(4);

  tr.innerHTML = `
    <td>
      <input type="text" class="ing-input item-name fw-semibold"
             value="${escH(item.name||'')}" oninput="onPrevNameInput(this)"
             placeholder="Nome ingrediente" required>
    </td>
    <td>
      <div class="ing-input-group">
        <span class="ing-input-addon">€</span>
        <input type="number" class="ing-input ing-input--mid item-price text-center"
               value="${fmtP(pricePerKg)}" step="0.0001" min="0.0001"
               oninput="updateGrandTotal()" required>
        <span class="ing-input-addon">/kg</span>
      </div>
    </td>
    <td>
      <div class="ing-input-group">
        <span class="ing-input-addon">€</span>
        <input type="number" class="ing-input ing-input--mid item-line-total text-center"
               value="${lineTotal.toFixed(4)}" step="0.0001" min="0"
               oninput="onLineTotalChange(this)">
      </div>
    </td>
    <td>${origHtml}</td>
    <td class="match-cell">${matchBadge(match)}</td>
    <td>
      <button type="button" class="ing-action-btn ing-action-btn--delete"
              onclick="removePrevRow(this)" title="Rimuovi" style="margin:0 auto;">
        <i class="bi bi-x-lg"></i>
      </button>
    </td>
  `;
  tbody.appendChild(tr);
}

function addManualPrevRow() {
  appendPrevRow({ name:'', price_per_kg:0, original_unit:'kg', original_qty:1,
                  original_price:0, original_price_raw:'', notes:'' });
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
  input.closest('tr').dataset.lineTotal = parseSafeFloat(input.value||0).toFixed(4);
  updateGrandTotal();
}

function matchBadge(match) {
  if (!match) return `<span class="ing-badge-new"><i class="bi bi-plus-lg"></i>Nuovo</span>`;
  return `<span class="ing-badge-update" title="Verrà aggiornato: ${escH(match.name)}">
    <i class="bi bi-arrow-repeat"></i>Aggiorna
    <small class="fw-normal d-block" style="font-size:.68rem;">${escH(match.name)}</small>
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
  let html = '';
  if (empty)   html += `<span style="color:var(--danger);margin-right:.75rem;">⚠ ${empty} senza nome</span>`;
  html += `<span style="color:var(--success);margin-right:.75rem;">✚ ${news} nuovi</span>`;
  html += `<span style="color:#92400e;">↻ ${updates} aggiornamenti</span>`;
  document.getElementById('prevMatchSummary').innerHTML = html;
}

function updateGrandTotal() {
  let total = 0;
  document.querySelectorAll('#prevItemsBody tr').forEach(tr => {
    total += parseSafeFloat(tr.dataset.lineTotal || 0);
  });
  document.getElementById('invGrandTotal').textContent = '€' + total.toFixed(4);
}


/* ═══════════════════════════════════════════════════════════
   SECTION 4 — SUBMIT INVOICE
═══════════════════════════════════════════════════════════ */

async function submitInvoiceData() {
  const rows  = document.querySelectorAll('#prevItemsBody tr');
  const items = [];

  for (const tr of rows) {
    const ni = tr.querySelector('.item-name');
    const pi = tr.querySelector('.item-price');
    const li = tr.querySelector('.item-line-total');
    if (!ni || !pi) continue;

    const name      = ni.value.trim();
    // BUG FIX: use parseSafeFloat to correctly handle decimal inputs (comma or period)
    const price     = parseSafeFloat(pi.value);
    const lineTotal = parseSafeFloat(li?.value || tr.dataset.lineTotal || 0);

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
    // BUG FIX: send price rounded to 4 decimal places as a proper float, NOT a string
    items.push({
      name,
      price_per_kg: Math.round(price * 10000) / 10000,
      line_total:   Math.round(lineTotal * 10000) / 10000
    });
  }

  if (items.length === 0) { alert('Nessun ingrediente da salvare.'); return; }

  const btn = document.getElementById('confirmInvBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvataggio in corso...';

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
      html += `<div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <span>${escH(data.message)}</span>
      </div>`;
      if (data.cost_entry) {
        html += `<div class="alert alert-info d-flex align-items-center gap-2 py-2">
          <i class="bi bi-receipt fs-5"></i>
          <span>
            <strong>Costo registrato:</strong> €${fmtP(data.cost_entry.amount)}
            nella categoria <strong>${escH(data.cost_entry.category)}</strong>
            — fornitore <strong>${escH(data.cost_entry.supplier)}</strong>
          </span>
        </div>`;
      }
      html += `<table class="ing-result-table">
        <thead><tr>
          <th class="text-start ps-3">Ingrediente</th>
          <th>Azione</th>
          <th>Prezzo / kg</th>
        </tr></thead><tbody>`;
      (data.details || []).forEach(d => {
        const badge   = d.action==='created'
          ? '<span class="ing-badge-new"><i class="bi bi-plus-lg"></i>Creato</span>'
          : '<span class="ing-badge-update"><i class="bi bi-arrow-repeat"></i>Aggiornato</span>';
        const matched = d.matched_to ? `<br><small class="text-muted">→ ${escH(d.matched_to)}</small>` : '';
        // BUG FIX: format the price correctly with period as decimal
        html += `<tr>
          <td class="text-start ps-3">${escH(d.name)}${matched}</td>
          <td class="text-center">${badge}</td>
          <td class="text-center"><span class="ing-price-badge">€${fmtP(d.price)}<span class="ing-price-unit">/kg</span></span></td>
        </tr>`;
      });
      html += '</tbody></table>';
    } else {
      html = `<div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <span>${escH(data.message||'Errore sconosciuto.')}</span>
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


/* ═══════════════════════════════════════════════════════════
   SECTION 5 — DYNAMIC ALIASES (Manual Form)
═══════════════════════════════════════════════════════════ */

function addAliasRow(value = '') {
  const wrapper = document.getElementById('aliasesWrapper');
  const div = document.createElement('div');
  div.className = 'ing-alias-row';
  div.innerHTML = `
    <i class="bi bi-arrow-right-short ing-alias-icon"></i>
    <input type="text" class="ing-input ing-input--sm alias-input"
           placeholder="es. White Sugar" value="${escH(value)}">
    <button type="button" class="ing-alias-remove" onclick="removeAliasRow(this)" title="Rimuovi">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  wrapper.appendChild(div);
  div.querySelector('input').focus();
}

function removeAliasRow(btn) {
  const wrapper = document.getElementById('aliasesWrapper');
  const row = btn.closest('.ing-alias-row');
  if (wrapper.querySelectorAll('.ing-alias-row').length > 1) {
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


/* ═══════════════════════════════════════════════════════════
   SECTION 6 — ALIAS MODAL
═══════════════════════════════════════════════════════════ */

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
  div.className = 'ing-alias-row';
  div.innerHTML = `
    <i class="bi bi-arrow-right-short ing-alias-icon"></i>
    <input type="text" class="ing-input ing-input--sm modal-alias-input"
           placeholder="es. White Sugar" value="${escH(value)}">
    <button type="button" class="ing-alias-remove" onclick="removeModalAliasRow(this)" title="Rimuovi">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  wrapper.appendChild(div);
  if (!value) div.querySelector('input').focus();
}

function removeModalAliasRow(btn) {
  const wrapper = document.getElementById('modalAliasesWrapper');
  const row = btn.closest('.ing-alias-row');
  if (wrapper.querySelectorAll('.ing-alias-row').length > 1) {
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
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrfToken(), 'Accept':'application/json' },
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


/* ═══════════════════════════════════════════════════════════
   SECTION 7 — CLIENT-SIDE MATCHING
═══════════════════════════════════════════════════════════ */

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


/* ═══════════════════════════════════════════════════════════
   SECTION 8 — DATATABLES INIT
═══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {

  const ingForm = document.getElementById('ingredientForm');
  if (ingForm) ingForm.addEventListener('submit', () => serializeAliases());

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
        emptyTable:   'Nessun ingrediente trovato.',
        search:       '_INPUT_',
        searchPlaceholder: 'Cerca ingredienti...',
        lengthMenu:   'Mostra _MENU_ per pagina',
        zeroRecords:  'Nessun risultato trovato.',
        info:         'Mostra _START_–_END_ di _TOTAL_ ingredienti',
        infoEmpty:    'Nessun ingrediente disponibile',
        paginate:     { first:'«', last:'»', next:'›', previous:'‹' },
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
      const idx    = $(this).index();
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


/* ═══════════════════════════════════════════════════════════
   SECTION 9 — UTILITIES
═══════════════════════════════════════════════════════════ */

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function escH(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

/**
 * BUG FIX — parseSafeFloat:
 * Handles both period (1.5) and comma (1,5) as decimal separators.
 * Prevents locale-based issues where Italian servers format "1.5" as "1,5"
 * and parseFloat("1,5000") would incorrectly return 1 instead of 1.5.
 */
function parseSafeFloat(v) {
  if (v === null || v === undefined || v === '') return 0;
  const s = String(v).trim();
  // If comma is used as decimal separator (e.g. "15,0000" or "1,50")
  // and no period is present → treat comma as decimal
  if (s.includes(',') && !s.includes('.')) {
    return parseFloat(s.replace(',', '.')) || 0;
  }
  // If both exist, assume comma = thousands separator (e.g. "1,500.00")
  if (s.includes(',') && s.includes('.')) {
    return parseFloat(s.replace(/,/g, '')) || 0;
  }
  return parseFloat(s) || 0;
}

/**
 * BUG FIX — fmtP:
 * Always formats to 4 decimal places using PERIOD as decimal separator.
 * This ensures the displayed price is unambiguous (e.g. "15.0000" not "15,0000").
 */
function fmtP(v) {
  const n = parseSafeFloat(v);
  return n.toFixed(4); // toFixed always uses period
}
</script>
@endsection