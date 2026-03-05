{{-- resources/views/frontend/recipe/show.blade.php --}}
@extends('frontend.layouts.app')

@section('title','Ricetta: '.$recipe->recipe_name)

@section('content')
<div class="container py-5">
  {{-- ✅ PDF capture area --}}
  <div class="card shadow-lg" id="pdf-area">
    <div class="card-header bg-dark text-gold d-flex align-items-center">
      <h5 class="mb-0" style="color: #e2ae76;">Ricetta: {{ $recipe->recipe_name }}</h5>
    </div>

    <div class="card-body">
      @php
        use Illuminate\Support\Facades\Auth;
        use App\Models\LaborCost;

        // 1) Unit selling price (GROSS)
        $unitSell = $recipe->sell_mode === 'piece'
                    ? $recipe->selling_price_per_piece
                    : $recipe->selling_price_per_kg;

        // VAT percent (adjust field name if needed)
        $vatPct = (float) ($recipe->vat_percent ?? $recipe->vat ?? 0);

        // 2) Batch ingredient cost (sum of all lines as stored)
        $batchIngCost = $recipe->ingredients_cost_per_batch;

        // 3) Batch labor cost (rate chosen by mode)
        $user         = Auth::user();
        $groupRootId  = $user->created_by ?: $user->id;
        $laborRateRec = LaborCost::where('user_id', $groupRootId)->first();
        $rate         = $recipe->labor_cost_mode === 'external'
                        ? ($laborRateRec->external_cost_per_min ?? 0)
                        : ($laborRateRec->shop_cost_per_min     ?? 0);
        $batchLabCost = round($recipe->labour_time_min * $rate, 2);

        // 4) Per-unit costs
        if ($recipe->sell_mode === 'piece') {
          $pcs         = $recipe->total_pieces ?: 1;
          $unitIngCost = $batchIngCost / $pcs;
          $unitLabCost = $batchLabCost / $pcs;
        } else {
          $wLoss       = $recipe->recipe_weight
                         ? $recipe->recipe_weight
                         : $recipe->ingredients->sum(fn($i) => $i->quantity_g);
          $kg          = max($wLoss / 1000, 1);
          $unitIngCost = $batchIngCost / $kg;
          $unitLabCost = $batchLabCost / $kg;
        }

        // 5) Expense & margins (already computed server-side)
        $unitTotalCost = $recipe->total_expense;           // includes labor in your stored logic
        $unitMargin    = $recipe->potential_margin;
        $unitMarginPct = $recipe->potential_margin_pct;

        // Base % based on GROSS price (your old behavior)
        $ingPct   = $unitSell > 0 ? round(($unitIngCost  * 100) / $unitSell, 2) : 0;
        $labPct   = $unitSell > 0 ? round(($unitLabCost  * 100) / $unitSell, 2) : 0;
        $totalPct = $unitSell > 0 ? round(($unitTotalCost * 100) / $unitSell, 2) : 0;

        // 6) Base totals for the table (ingredients)
        $totalQty  = (int) ($recipe->recipe_weight ?: $recipe->ingredients->sum(fn($ri) => $ri->quantity_g));
        $totalCost = $recipe->ingredients->sum(fn($ri) => round(($ri->quantity_g/1000)*$ri->ingredient->price_per_kg, 2));

        // store originals for multiplier UI
        $origIng = (float) $totalCost;     // ingredients total €
        $origLab = (float) $batchLabCost;  // labor total €
      @endphp

      {{-- KPIs --}}
      <div class="row text-center mb-4">
        <div class="col-md-2">
          <strong>Prezzo</strong><br>
          <div class="d-flex flex-column align-items-center">
            <span id="kpi-price"
                  data-base="{{ number_format($unitSell,2,'.','') }}">
              €{{ number_format($unitSell,2) }}
            </span>
            <small class="text-muted" id="kpi-price-pct">(100%)</small>
          </div>
        </div>

        <div class="col-md-2">
          <strong>Costo ingr.</strong><br>
          <div class="d-flex flex-column align-items-center">
            <span id="kpi-ing"
                  data-base="{{ number_format($unitIngCost,2,'.','') }}">
              €{{ number_format($unitIngCost,2) }}
            </span>
            <small class="text-muted" id="kpi-ing-pct">
              ({{ $ingPct }}%)
            </small>
          </div>
        </div>

        <div class="col-md-2">
          <strong>Costo lavoro</strong><br>
          <div class="d-flex flex-column align-items-center">
            <span id="kpi-lab"
                  data-base="{{ number_format($unitLabCost,2,'.','') }}">
              €{{ number_format($unitLabCost,2) }}
            </span>
            <small class="text-muted" id="kpi-lab-pct">
              ({{ $labPct }}%)
            </small>
          </div>
        </div>

        <div class="col-md-2">
          <strong>Costo totale</strong><br>
          <div class="d-flex flex-column align-items-center">
            <span id="kpi-total"
                  data-base="{{ number_format($unitTotalCost,2,'.','') }}">
              €{{ number_format($unitTotalCost,2) }}
            </span>
            <small class="text-muted" id="kpi-total-pct">
              ({{ $totalPct }}%)
            </small>
          </div>
        </div>

        <div class="col-md-2">
          <strong>Margine</strong><br>
          <div class="d-flex flex-column align-items-center">
            <span id="kpi-margin"
                  data-vat="{{ number_format($vatPct,2,'.','') }}"
                  class="{{ $unitMargin >= 0 ? 'text-success' : 'text-danger' }}">
              €{{ number_format($unitMargin,2) }}
            </span>
            <small class="text-muted" id="kpi-margin-pct">
              ({{ number_format($unitMarginPct,2) }}%)
            </small>
          </div>
        </div>

        <div class="col-md-2">
          <strong>% margine</strong><br>
          <span id="kpi-margin-pct-2">{{ number_format($unitMarginPct,2) }}%</span>
        </div>
      </div>

      <hr>

      {{-- Dettaglio ingredienti --}}
      <h6>Dettaglio ingredienti</h6>
      <div class="table-responsive">
        <table data-page-length="25" class="table table-bordered mb-0" id="ingredients-detail-table">
          <thead class="table-light">
            <tr>
              <th>Ingrediente</th>
              <th class="text-end">Qtà (g)</th>
              <th class="text-end">Costo (€)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recipe->ingredients as $ri)
              @php
                $lineBaseQty  = (float) $ri->quantity_g;
                $lineBaseCost = round(($lineBaseQty/1000) * $ri->ingredient->price_per_kg, 2);
              @endphp
              <tr>
                <td>{{ $ri->ingredient->ingredient_name }}</td>

                <td class="text-end qty-cell"
                    data-base-qty="{{ number_format($lineBaseQty, 3, '.', '') }}">
                  {{ number_format($lineBaseQty, 0) }}
                </td>

                <td class="text-end cost-cell"
                    data-base-cost="{{ number_format($lineBaseCost,2,'.','') }}">
                  €{{ number_format($lineBaseCost,2) }}
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot class="table-warning">
            <tr>
              <td class="text-end"><strong>Totale:</strong></td>
              <td class="text-end">
                <strong id="qty-total"
                        data-base-qty="{{ number_format($totalQty, 3, '.', '') }}">
                  {{ number_format($totalQty, 0) }}
                </strong>
              </td>
              <td class="text-end">
                <strong id="ingredients-total"
                        data-base-total="{{ number_format($origIng,2,'.','') }}">
                  €{{ number_format($origIng,2) }}
                </strong>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      {{-- Labor, grand total & multiplier --}}
      <div class="mt-3">
        <p>
          <strong>Totale manodopera (÷ moltiplicatore):</strong>
          €<span id="labor-total" data-base="{{ number_format($origLab,2,'.','') }}">
            {{ number_format($origLab,2) }}
          </span>
        </p>
        <p>
          <strong>Totale (ingredienti × moltiplicatore + manodopera ÷ moltiplicatore):</strong>
          <span id="total-with-labor">
            €{{ number_format($origIng + $origLab,2) }}
          </span>
        </p>
        <div class="d-flex align-items-center">
          <label for="multiplier" class="me-2 mb-0"><strong>Moltiplicatore:</strong></label>
          <input type="number" id="multiplier" step="0.01" value="1" style="width: 100px;">
        </div>
        <small class="text-muted d-block mt-1">
          Il moltiplicatore modifica le quantità/costi degli ingredienti e divide la manodopera per il moltiplicatore.
          Solo visualizzazione: non viene salvato.
        </small>
      </div>

      {{-- Actions (ignored in pdf) --}}
      <div class="mt-4 text-end" data-html2canvas-ignore="true">
        <button type="button" id="btn-save-pdf" class="btn btn-outline-gold me-2">
          Save as PDF
        </button>

        <a href="{{ route('recipes.edit',$recipe->id) }}" class="btn btn-outline-gold">Modifica</a>
        <a href="{{ route('recipes.index') }}" class="btn btn-outline-deepblue">Indietro</a>
      </div>
    </div>
  </div>
</div>

<style>
  .btn-outline-gold { border: 1px solid #e2ae76; color: #e2ae76; }
  .btn-outline-gold:hover { background: #e2ae76; color: #fff; }
  .btn-outline-deepblue { border: 1px solid #041930; color: #041930; }
  .btn-outline-deepblue:hover { background: #041930; color: #fff; }
</style>

{{-- PDF libs --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>
(function() {
  const mInput = document.getElementById('multiplier');

  // ingredient table
  const qtyTds  = document.querySelectorAll('#ingredients-detail-table td.qty-cell');
  const costTds = document.querySelectorAll('#ingredients-detail-table td.cost-cell');

  const qtyTotalEl = document.getElementById('qty-total');
  const ingTotalEl = document.getElementById('ingredients-total');

  // bottom totals
  const laborEl = document.getElementById('labor-total');
  const totalEl = document.getElementById('total-with-labor');

  // KPIs
  const kPrice  = document.getElementById('kpi-price');
  const kIng    = document.getElementById('kpi-ing');
  const kLab    = document.getElementById('kpi-lab');
  const kTotal  = document.getElementById('kpi-total');
  const kMargin = document.getElementById('kpi-margin');

  const kIngPct    = document.getElementById('kpi-ing-pct');
  const kLabPct    = document.getElementById('kpi-lab-pct');
  const kTotalPct  = document.getElementById('kpi-total-pct');
  const kMarginPct = document.getElementById('kpi-margin-pct');
  const kMarginPct2= document.getElementById('kpi-margin-pct-2');

  function euro(n){ return '€' + Number(n || 0).toFixed(2); }
  function num2(n){ return Number(n || 0).toFixed(2); }
  function i(n){ return Math.round(Number(n || 0)); }

  function recalc() {
    const m = parseFloat(mInput.value);
    const mult = (isFinite(m) && m > 0) ? m : 1;

    // Ingredient table scaling
    let newQtyTotal = 0;
    let newIngTotal = 0;

    qtyTds.forEach(td => {
      const baseQ = parseFloat(td.dataset.baseQty) || 0;
      const q = baseQ * mult;
      newQtyTotal += q;
      td.textContent = i(q);
    });

    costTds.forEach(td => {
      const baseC = parseFloat(td.dataset.baseCost) || 0;
      const c = baseC * mult;
      newIngTotal += c;
      td.textContent = euro(c);
    });

    if (qtyTotalEl) qtyTotalEl.textContent = i(newQtyTotal);
    if (ingTotalEl) ingTotalEl.textContent = euro(newIngTotal);

    // Client rule: labor = labor / multiplier
    const baseLabBatch = parseFloat(laborEl.dataset.base) || 0;
    const adjLabBatch  = baseLabBatch / mult;

    laborEl.textContent = num2(adjLabBatch);

    // Client rule: total = ingredientCost + labor/m
    totalEl.textContent = euro(newIngTotal + adjLabBatch);

    // KPIs: margin uses NET price (remove VAT)
    const grossPrice = parseFloat(kPrice?.dataset.base) || 0;
    const vatPct = parseFloat(kMargin?.dataset.vat) || 0;
    const netPrice = vatPct > 0 ? (grossPrice / (1 + vatPct/100)) : grossPrice;

    const baseUnitIng   = parseFloat(kIng?.dataset.base)   || 0;
    const baseUnitLab   = parseFloat(kLab?.dataset.base)   || 0;
    const baseUnitTotal = parseFloat(kTotal?.dataset.base) || 0;

    // cost of entry = everything except labor (keep extra expenses stable)
    const baseWithoutLabor = baseUnitTotal - baseUnitLab;

    const adjUnitLab   = baseUnitLab / mult;
    const adjUnitTotal = baseWithoutLabor + adjUnitLab;

    const ingPct = netPrice > 0 ? (baseUnitIng * 100) / netPrice : 0;
    const labPct = netPrice > 0 ? (adjUnitLab   * 100) / netPrice : 0;
    const totPct = netPrice > 0 ? (adjUnitTotal * 100) / netPrice : 0;

    const margin = netPrice - adjUnitTotal;
    const marginPct = netPrice > 0 ? (margin * 100) / netPrice : 0;

    // Update KPI numbers
    if (kLab)   kLab.textContent   = euro(adjUnitLab);
    if (kTotal) kTotal.textContent = euro(adjUnitTotal);

    if (kIngPct)   kIngPct.textContent   = '(' + ingPct.toFixed(2) + '%)';
    if (kLabPct)   kLabPct.textContent   = '(' + labPct.toFixed(2) + '%)';
    if (kTotalPct) kTotalPct.textContent = '(' + totPct.toFixed(2) + '%)';

    if (kMargin) {
      kMargin.textContent = euro(margin);
      kMargin.classList.remove('text-success','text-danger');
      kMargin.classList.add(margin >= 0 ? 'text-success' : 'text-danger');
    }

    if (kMarginPct)  kMarginPct.textContent  = '(' + marginPct.toFixed(2) + '%)';
    if (kMarginPct2) kMarginPct2.textContent = marginPct.toFixed(2) + '%';
  }

  recalc();
  mInput.addEventListener('input', recalc);
  mInput.addEventListener('change', recalc);
})();
</script>

<script>
(function () {
  const btn  = document.getElementById('btn-save-pdf');
  const area = document.getElementById('pdf-area');

  function safeName(str){
    return String(str || '')
      .replace(/[^a-z0-9\-_]+/gi, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '')
      .toLowerCase();
  }

  if (!btn || !area) return;

  btn.addEventListener('click', async function () {
    const oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    try {
      const canvas = await html2canvas(area, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        scrollY: -window.scrollY,
      });

      const imgData = canvas.toDataURL('image/png', 1.0);

      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF('p', 'mm', 'a4');

      const pageWidth  = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();

      const imgWidth  = pageWidth;
      const imgHeight = (canvas.height * imgWidth) / canvas.width;

      let heightLeft = imgHeight;
      let position = 0;

      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      heightLeft -= pageHeight;

      while (heightLeft > 0) {
        position -= pageHeight;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
      }

      const fileName = `recipe-{{ $recipe->id }}-${safeName(@json($recipe->recipe_name))}.pdf`;
      pdf.save(fileName);
    } catch (e) {
      console.error(e);
      alert('PDF generation failed. Please try again.');
    } finally {
      btn.disabled = false;
      btn.textContent = oldText;
    }
  });
})();
</script>
@endsection