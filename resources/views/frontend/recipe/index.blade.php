{{-- resources/views/frontend/recipe/index.blade.php --}}
@extends('frontend.layouts.app')

@section('title', 'Todas las recetas')

@section('content')
    <style>
        /* eliminar todas las flechas de cabecera con máxima especificidad */
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.sorting,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.sorting_asc,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.sorting_desc,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.dt-orderable-asc,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.dt-orderable-desc,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.dt-ordering-asc,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th.dt-ordering-desc {
            background-image: none !important
        }

        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th:before,
        .dataTables_wrapper table#recipesTable thead.custom-recipe-head th:after {
            content: none !important;
            display: none !important
        }

        .dt-column-order {
            /* visibility:hidden; */
        }

        /* tarjeta de estadísticas compacta */
        .stat-card {
            border: 1px dashed #e2ae76;
            border-radius: .75rem;
            padding: 12px
        }

        .stat-title {
            font-weight: 700;
            color: #041930
        }

        .stat-value {
            font-size: 1.15rem;
            font-weight: 800
        }
    </style>

    <div class="container py-5">

        <!-- Cabecera -->
        <div class="page-header d-flex align-items-center mb-4 p-4 rounded" style="background-color:#041930;">
            <i class="bi bi-bookmark-star-fill me-3 fs-3" style="color:#e2ae76;"></i>
            <div>
                <h4 class="mb-0 fw-bold" style="color:#e2ae76;">Todas las recetas</h4>
                <small class="d-block text-light">Busca, ordena y filtra rápidamente todas tus recetas aquí abajo.</small>
            </div>
        </div>

        <!-- Tarjeta con filtros + tabla -->
        <div class="card shadow-sm">
            <div class="card-body">

                <!-- Filtros + Orden + Media compacta -->
                <div class="row g-3 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label for="sellModeFilter" class="form-label fw-semibold">Filtrar por modalidad de venta</label>
                        <select id="sellModeFilter" class="form-select">
                            <option value="">Todas</option>
                            <option value="piece">Pieza</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="categoryFilter" class="form-label fw-semibold">Filtrar por categoría</label>
                        <select id="categoryFilter" class="form-select">
                            <option value="">Todas</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="sortBy" class="form-label fw-semibold">Ordenar por</label>
                        <select id="sortBy" class="form-select">
                            <option value="">Predeterminado</option>
                            <option value="name_asc">Nombre ↑</option>
                            <option value="name_desc">Nombre ↓</option>
                            <option value="salesmode_asc">Modalidad de venta ↑</option>
                            <option value="salesmode_desc">Modalidad de venta ↓</option>
                            <option value="price_asc">Precio ↑</option>
                            <option value="price_desc">Precio ↓</option>
                            <option value="entrycost_asc">Costo ingr. ↑</option>
                            <option value="entrycost_desc">Costo ingr. ↓</option>
                            <option value="labourcost_asc">Costo mano de obra ↑</option>
                            <option value="labourcost_desc">Costo mano de obra ↓</option>
                            <option value="totalcost_asc">Costo total ↑</option>
                            <option value="totalcost_desc">Costo total ↓</option>
                            <option value="margin_asc">Margen ↑</option>
                            <option value="margin_desc">Margen ↓</option>
                        </select>
                    </div>

                    <!-- Media en vivo (excluye negativos) — desplegable eliminado -->
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="stat-title">Media de margen</div>
                            </div>
                            <div class="stat-value mt-1">
                                <span id="avgMarginValue">—</span><span>%</span>
                            </div>
                            <small class="text-muted" id="avgMarginHelp">Negativos excluidos.</small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="recipesTable" class="table table-striped table-hover table-bordered mb-0"
                        style="width:100%;" data-page-length="25">
                        <thead class="custom-recipe-head">
                            <tr class="text-center">
                                <th class="sortable">Nombre</th>
                                <th class="sortable">Modalidad de venta</th>
                                <th class="text-end sortable">Precio</th>
                                <th class="text-end sortable">Costo ingr.</th>
                                <th class="text-end sortable">Costo mano de obra</th>
                                <th class="text-end sortable">Costo total</th>
                                <th class="text-end sortable">Margen</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recipes as $r)
                                @php
                                    /*
                                     * IMPORTANTE: todos los valores por unidad (ingredientes, mano de obra, embalaje, total)
                                     * se recalculan en vivo aquí desde los datos actuales para evitar totales obsoletos.
                                     */

                                    // 1) Precio de venta (bruto + neto)
                                    $unitSell =
                                        $r->sell_mode === 'piece'
                                            ? $r->selling_price_per_piece ?? 0
                                            : $r->selling_price_per_kg ?? 0;
                                    $vatRate = (float) ($r->vat_rate ?? 0);
                                    $netSell = $vatRate ? $unitSell / (1 + $vatRate / 100) : $unitSell;

                                    // 2) Costo de mano de obra del lote (ya calculado en el controlador con tarifas efectivas actuales):
                                    //    $r->batch_labor_cost = (minutos * €/min)
                                    $batchLabCost = (float) ($r->batch_labor_cost ?? 0);

                                    // Convertir mano de obra por lote -> por unidad
                                    if ($r->sell_mode === 'piece') {
                                        $pcs = ($r->total_pieces ?? 0) > 0 ? (int) $r->total_pieces : 1;
                                        $unitLabCost = $batchLabCost / $pcs;
                                    } else {
                                        $wLoss = (float) ($r->recipe_weight ?? 0);
                                        if ($wLoss <= 0) {
                                            // respaldo a la suma de gramos de ingredientes si recipe_weight está vacío
                                            $wLoss = $r->ingredients->sum(function ($i) {
                                                return (float) $i->quantity_g;
                                            });
                                        }
                                        $kg = $wLoss > 0 ? $wLoss / 1000 : 1;
                                        $unitLabCost = $batchLabCost / $kg;
                                    }

                                    // 3) Costes de ingredientes EN VIVO desde precios actuales de ingredientes
                                    $ingredientsData = $r->ingredients
                                        ->map(function ($ri) {
                                            $priceKg = (float) (optional($ri->ingredient)->price_per_kg ?? 0);
                                            $cost = round(($ri->quantity_g / 1000) * $priceKg, 2);
                                            return [
                                                'name' => optional($ri->ingredient)->ingredient_name ?? '—',
                                                'qty_g' => (float) $ri->quantity_g,
                                                'cost' => $cost,
                                            ];
                                        })
                                        ->values();

                                    $batchIngCost = collect($ingredientsData)->sum('cost'); // € para todo el lote

                                    // Convertir ingredientes por lote -> por unidad
                                    if ($r->sell_mode === 'piece') {
                                        $pcs = ($r->total_pieces ?? 0) > 0 ? (int) $r->total_pieces : 1;
                                        $unitIngCost = $pcs > 0 ? $batchIngCost / $pcs : 0;
                                    } else {
                                        $wLoss = (float) ($r->recipe_weight ?? 0);
                                        if ($wLoss <= 0) {
                                            $wLoss = $r->ingredients->sum(function ($i) {
                                                return (float) $i->quantity_g;
                                            });
                                        }
                                        $kg = $wLoss > 0 ? $wLoss / 1000 : 1;
                                        $unitIngCost = $batchIngCost / $kg;
                                    }

                                    // 4) Embalaje por unidad
                                    $pack = (float) ($r->packing_cost ?? 0);
                                    if ($r->sell_mode === 'piece') {
                                        $pcs = ($r->total_pieces ?? 0) > 0 ? (int) $r->total_pieces : 1;
                                        $unitPackCost = $pcs > 0 ? $pack / $pcs : 0;
                                    } else {
                                        // el embalaje ya es por kg en modo KG
                                        $unitPackCost = $pack;
                                    }

                                    // 5) COSTE total final por unidad (EN VIVO)
                                    $unitTotalCost = round($unitIngCost + $unitLabCost + $unitPackCost, 2);

                                    // 6) Margen (por unidad) y porcentajes respecto al precio de venta neto
                                    $unitMargin = round($netSell - $unitTotalCost, 2);
                                    
                                    // ✅ CORRECCIÓN: Calcular el porcentaje correctamente incluso para márgenes negativos
                                    $ingPct = $netSell > 0 ? round(($unitIngCost * 100) / $netSell, 2) : 0;
                                    $labPct = $netSell > 0 ? round(($unitLabCost * 100) / $netSell, 2) : 0;
                                    $totalPct = $netSell > 0 ? round(($unitTotalCost * 100) / $netSell, 2) : 0;
                                    
                                    // ✅ CORRECCIÓN CRÍTICA: Permitir porcentajes negativos para el margen
                                    // Este era el error: cuando unitMargin es negativo, marPct también debe ser negativo
                                    $marPct = $netSell > 0 ? round(($unitMargin * 100) / $netSell, 2) : 0;
                                @endphp

                                <tr class="dt-control text-center" data-sell-mode="{{ $r->sell_mode }}"
                                    data-category="{{ optional($r->category)->id ?? '' }}"
                                    data-margin-pct="{{ $marPct }}" data-ingredients='@json($ingredientsData)'>
                                    <td class="text-start">{{ $r->recipe_name }}</td>
                                    <td><span class="badge bg-secondary text-uppercase">{{ $r->sell_mode }}</span></td>

                                    <td class="text-end" data-order="{{ $unitSell }}">
                                        <div class="d-flex flex-column align-items-end">
                                            <span>€{{ number_format($unitSell, 2) }}</span>
                                            <small class="text-muted">({{ number_format($netSell, 2) }})</small>
                                        </div>
                                    </td>

                                    <td class="text-end" data-order="{{ $unitIngCost }}">
                                        <div class="d-flex flex-column align-items-end">
                                            <span>€{{ number_format($unitIngCost, 2) }}</span>
                                            <small class="text-muted">({{ $ingPct }}%)</small>
                                        </div>
                                    </td>

                                    <td class="text-end" data-order="{{ $unitLabCost }}">
                                        <div class="d-flex flex-column align-items-end">
                                            <span>€{{ number_format($unitLabCost, 2) }}</span>
                                            <small class="text-muted">({{ $labPct }}%)</small>
                                        </div>
                                    </td>

                                    <td class="text-end" data-order="{{ $unitTotalCost }}">
                                        <div class="d-flex flex-column align-items-end">
                                            <span>€{{ number_format($unitTotalCost, 2) }}</span>
                                            <small class="text-muted">({{ $totalPct }}%)</small>
                                        </div>
                                    </td>

                                    <td class="text-end" data-order="{{ $unitMargin }}">
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="{{ $unitMargin >= 0 ? 'text-success' : 'text-danger' }}">
                                                €{{ number_format($unitMargin, 2) }}
                                            </span>
                                            <small class="{{ $marPct >= 0 ? 'text-muted' : 'text-danger' }}">({{ $marPct }}%)</small>
                                        </div>
                                    </td>

                                    <td>
                                        <!-- Editar -->
                                        <a href="{{ route('recipes.edit', $r) }}" class="btn btn-sm me-1"
                                            style="border:1px solid #e2ae76;color:#e2ae76;"
                                            onmouseover="this.style.backgroundColor='#e2ae76';this.style.color='#fff';"
                                            onmouseout="this.style.backgroundColor='transparent';this.style.color='#e2ae76';">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Ver -->
                                        <a href="{{ route('recipes.show', $r) }}" class="btn btn-sm me-1"
                                            style="border:1px solid #041930;color:#041930;"
                                            onmouseover="this.style.backgroundColor='#041930';this.style.color='#fff';"
                                            onmouseout="this.style.backgroundColor='transparent';this.style.color='#041930';">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Duplicar -->
                                        <form action="{{ route('recipes.duplicate', $r) }}" method="POST"
                                            class="d-inline me-1" onsubmit="return confirm('¿Duplicar esta receta?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm"
                                                style="border:1px solid #6c757d;color:#6c757d;"
                                                onmouseover="this.style.backgroundColor='#6c757d';this.style.color='#fff';"
                                                onmouseout="this.style.backgroundColor='transparent';this.style.color='#6c757d';">
                                                <i class="bi bi-files"></i>
                                            </button>
                                        </form>

                                        <!-- Eliminar -->
                                        <form action="{{ route('recipes.destroy', $r) }}" method="POST"
                                            class="d-inline" onsubmit="return confirm('¿Eliminar esta receta?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm"
                                                style="border:1px solid #ff0000;color:#ff0000;"
                                                onmouseover="this.style.backgroundColor='#ff0000';this.style.color='#fff';"
                                                onmouseout="this.style.backgroundColor='transparent';this.style.color='#ff0000';">
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

<style>
    table#recipesTable thead.custom-recipe-head th {
        background-color: #e2ae76 !important;
        color: #041930 !important;
        text-align: center;
        cursor: pointer
    }

    #recipesTable thead th:last-child {
        cursor: default
    }

    /* Ocultar flechas de ordenación de DataTables (clásico + nuevos skins) */
    table.dataTable thead .sorting:before,
    table.dataTable thead .sorting:after,
    table.dataTable thead .sorting_asc:before,
    table.dataTable thead .sorting_asc:after,
    table.dataTable thead .sorting_desc:before,
    table.dataTable thead .sorting_desc:after,
    table.dataTable thead th.dt-orderable-asc:before,
    table.dataTable thead th.dt-orderable-desc:after,
    table.dataTable thead th.dt-ordering-asc:before,
    table.dataTable thead th.dt-ordering-desc:after {
        display: none !important
    }
</style>

@section('scripts')
    <script>
        $(function() {
            const storageKey = 'recipesTableState';

            const table = $('#recipesTable').DataTable({
                paging: true,
                ordering: true,
                responsive: true,
                pageLength: 10,
                orderMulti: false,
                order: [
                    [0, 'asc']
                ],
                columnDefs: [{
                        orderable: false,
                        targets: -1
                    },
                    {
                        targets: '_all',
                        orderSequence: ['asc', 'desc']
                    }
                ],
                stateSave: true,
                stateDuration: 0,
                stateSaveCallback: function(settings, data) {
                    try {
                        sessionStorage.setItem(storageKey, JSON.stringify(data));
                    } catch (e) {}
                },
                stateLoadCallback: function() {
                    try {
                        return JSON.parse(sessionStorage.getItem(storageKey));
                    } catch (e) {
                        return null;
                    }
                },
                language: {
                    lengthMenu: "Mostrar _MENU_ elementos por página",
                    search: "Buscar:",
                    searchPlaceholder: "Buscar recetas..."
                }
            });

            // --- calcular media del % de margen sobre filas visibles; excluir negativos
            function updateAvgMarginCard() {
                const allVisibleNodes = table.rows({ filter: 'applied' }).nodes().toArray();

                let sum = 0, count = 0;
                allVisibleNodes.forEach(r => {
                    const v = parseFloat($(r).attr('data-margin-pct'));
                    // ✅ CORRECCIÓN: ahora excluye correctamente márgenes negativos
                    if (!isNaN(v) && v >= 0) {
                        sum += v;
                        count++;
                    }
                });

                if (count === 0) {
                    $('#avgMarginValue').text('—');
                    $('#avgMarginHelp').text('Ningún elemento con margen ≥ 0 en la vista actual.');
                } else {
                    const avg = (sum / count).toFixed(2);
                    $('#avgMarginValue').text(avg);
                    $('#avgMarginHelp').text(`Calculada sobre ${count} producto/s (negativos excluidos).`);
                }
            }

            // Filtros personalizados de DataTables (modalidad de venta + categoría en tabla)
            $.fn.dataTable.ext.search.push((settings, data, rowIndex) => {
                if (settings.nTable.id !== 'recipesTable') return true;

                const sellMode = $('#sellModeFilter').val();
                const catFilter = $('#categoryFilter').val();

                const row = table.row(rowIndex).node();
                const modeOk = !sellMode || ($(row).data('sell-mode') === sellMode);

                const rowCat = ($(row).data('category') ?? '').toString();
                const catOk = !catFilter || rowCat === catFilter;

                return modeOk && catOk;
            });

            // Recalcular y redibujar al cambiar filtros
            $('#sellModeFilter, #categoryFilter').on('change', function() {
                table.draw();
                updateAvgMarginCard();
            });

            // Mantener desplegable sincronizado + mantener media actualizada
            const mapToKey = (col, dir) => {
                const map = {
                    0: { asc: 'name_asc',       desc: 'name_desc' },
                    1: { asc: 'salesmode_asc',  desc: 'salesmode_desc' },
                    2: { asc: 'price_asc',      desc: 'price_desc' },
                    3: { asc: 'entrycost_asc',  desc: 'entrycost_desc' },
                    4: { asc: 'labourcost_asc', desc: 'labourcost_desc' },
                    5: { asc: 'totalcost_asc',  desc: 'totalcost_desc' },
                    6: { asc: 'margin_asc',     desc: 'margin_desc' }
                };
                return (map[col] && map[col][dir]) ? map[col][dir] : '';
            };
            const mapFromKey = (key) => {
                const pairs = {
                    'name_asc': [0, 'asc'], 'name_desc': [0, 'desc'],
                    'salesmode_asc': [1, 'asc'], 'salesmode_desc': [1, 'desc'],
                    'price_asc': [2, 'asc'], 'price_desc': [2, 'desc'],
                    'entrycost_asc': [3, 'asc'], 'entrycost_desc': [3, 'desc'],
                    'labourcost_asc': [4, 'asc'], 'labourcost_desc': [4, 'desc'],
                    'totalcost_asc': [5, 'asc'], 'totalcost_desc': [5, 'desc'],
                    'margin_asc': [6, 'asc'], 'margin_desc': [6, 'desc']
                };
                return pairs[key] || null;
            };

            const syncDropdownWithOrder = () => {
                const ord = table.order();
                if (ord && ord.length) {
                    const key = mapToKey(ord[0][0], ord[0][1]);
                    $('#sortBy').val(key);
                } else {
                    $('#sortBy').val('');
                }
            };
            table.on('draw', function() {
                syncDropdownWithOrder();
                updateAvgMarginCard();
            });
            syncDropdownWithOrder();
            updateAvgMarginCard();

            // Desplegable -> aplicar orden + persistir
            $('#sortBy').on('change', function() {
                const key = $(this).val();
                const ord = mapFromKey(key) || [0, 'asc'];
                table.order([ord]).draw();
                try {
                    const s = JSON.parse(sessionStorage.getItem(storageKey)) || {};
                    s.order = table.order();
                    sessionStorage.setItem(storageKey, JSON.stringify(s));
                } catch (e) {}
            });

            // Evitar multi-orden con shift
            $('#recipesTable thead').on('mousedown', 'th', function(e) {
                if (e.shiftKey) e.preventDefault();
            });

            // Toggle de fila hija
            $('#recipesTable tbody').on('click', 'tr.dt-control', function() {
                const tr = $(this),
                    row = table.row(tr),
                    ingredients = JSON.parse(tr.attr('data-ingredients') || '[]');

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    let html = '<table data-page-length="25" class="table table-borderless mb-0"><thead><tr>' +
                        '<th>Ingrediente</th><th class="text-end">Cant. (g)</th><th class="text-end">Costo</th>' +
                        '</tr></thead><tbody>';
                    ingredients.forEach(i => {
                        const name = i.name ?? '—';
                        const qty = Number(i.qty_g ?? 0);
                        const cost = Number(i.cost ?? 0);
                        html +=
                            `<tr><td>${name}</td><td class="text-end">${qty}</td><td class="text-end">€${cost.toFixed(2)}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    row.child(html).show();
                    tr.addClass('shown');
                }
            });
        });
    </script>
@endsection
