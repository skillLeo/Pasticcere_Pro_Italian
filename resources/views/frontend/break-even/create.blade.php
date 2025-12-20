@extends('frontend.layouts.app')

@section('title','Crear proveedor externo')

@section('content')
<div class="container py-5">
    <form method="POST" action="#">
        @csrf
        {{-- 1) Detalles del proveedor y del producto --}}
        <div class="card mb-4 border-primary shadow-sm">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-truck fs-4 me-2"></i>
                <h5 class="mb-0">Detalles del proveedor y del producto</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="supplierName" class="form-label fw-semibold">Nombre del proveedor</label>
                        <input type="text" id="supplierName" name="supplier_name" class="form-control" placeholder="ABC Suppliers" required>
                    </div>
                    <div class="col-md-6">
                        <label for="productName" class="form-label fw-semibold">Nombre del producto</label>
                        <input type="text" id="productName" name="product_name" class="form-control" placeholder="Tarta de chocolate importada" required>
                    </div>
                    <div class="col-md-6">
                        <label for="productCategory" class="form-label fw-semibold">Categoría</label>
                        <input type="text" id="productCategory" name="category" class="form-control" placeholder="Postre" required>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2) Detalles de compra (en lugar de ingredientes) --}}
        <div class="card mb-4 border-info shadow-sm">
            <div class="card-header bg-info text-white d-flex align-items-center">
                <i class="bi bi-cash-stack fs-4 me-2"></i>
                <h5 class="mb-0">Detalles de compra</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="purchaseCostPerKg" class="form-label fw-semibold">Costo por kg (€)</label>
                        <input type="number" step="0.01" id="purchaseCostPerKg" name="purchase_cost_per_kg" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6">
                        <label for="totalWeightPurchased" class="form-label fw-semibold">Peso total comprado (kg)</label>
                        <input type="number" step="0.01" id="totalWeightPurchased" name="total_weight_purchased" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6">
                        <label for="totalPurchaseCost" class="form-label fw-semibold">Costo total de compra (€)</label>
                        <input type="text" id="totalPurchaseCost" name="total_purchase_cost" class="form-control" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3) Mano de obra --}}
        <div class="card mb-4 border-warning shadow-sm">
            <div class="card-header bg-warning text-dark d-flex align-items-center">
                <i class="bi bi-clock-history fs-4 me-2"></i>
                <h5 class="mb-0">Mano de obra</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="laborTimeInput" class="form-label fw-semibold">Tiempo activo de preparación (min)</label>
                        <input type="number" id="laborTimeInput" name="labor_time_input" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="costPerMin" class="form-label fw-semibold">Costo por minuto</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="text" id="costPerMin" class="form-control" value="{{ optional($laborCost)->cost_per_minute ?? '0.00' }}" readonly required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="laborCost" class="form-label fw-semibold">Costo de mano de obra (€)</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="text" id="laborCost" name="labor_cost" class="form-control" readonly required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4) Totales, modo de venta y ajustes de costes --}}
        <div class="row gx-4 mb-4">
            {{-- Gasto total y empaque --}}
            <div class="col-md-6">
                <div class="card border-success shadow-sm h-100">
                    <div class="card-header bg-success text-white d-flex align-items-center">
                        <i class="bi bi-calculator fs-4 me-2"></i>
                        <h5 class="mb-0">Gasto total</h5>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center">
                        {{-- Costo de empaque --}}
                        <div class="input-group w-75 mb-3">
                            <span class="input-group-text">Empaque</span>
                            <span class="input-group-text">€</span>
                            <input type="number" step="0.01" id="packingCost" name="packing_cost" class="form-control text-end" value="0.00">
                        </div>

                        {{-- Suma de gastos --}}
                        <div class="input-group input-group-lg w-75 mb-3">
                            <span class="input-group-text">€</span>
                            <input type="text" id="totalExpense" name="total_expense" class="form-control fw-bold text-center" readonly required>
                        </div>

                        {{-- Cálculo de punto de equilibrio ajustado --}}
                        <div class="w-75 text-center">
                            <span class="fw-semibold">Punto de equilibrio ajustado:</span>
                            <span id="adjustedBreakEven" class="fw-bold ms-2"></span>
                            <input type="hidden" name="adjusted_break_even" id="adjustedBreakEvenInput">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modo de venta e inputs adicionales --}}
            <div class="col-md-6">
                <div class="card border-secondary shadow-sm h-100">
                    <div class="card-header bg-secondary text-white d-flex align-items-center">
                        <i class="bi bi-shop fs-4 me-2"></i>
                        <h5 class="mb-0">Modo de venta</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sell_mode" id="modePiece" value="piece" checked>
                                <label class="form-check-label" for="modePiece">Vender por pieza</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sell_mode" id="modeKg" value="kg">
                                <label class="form-check-label" for="modeKg">Vender por kg</label>
                            </div>
                        </div>

                        {{-- Inputs por pieza --}}
                        <div id="pieceInputs">
                            <div class="mb-3">
                                <label for="totalPieces" class="form-label fw-semibold">Piezas totales</label>
                                <input type="number" id="totalPieces" name="total_pieces" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="pricePerPiece" class="form-label fw-semibold">Precio de venta por pieza (€)</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="number" step="0.01" id="pricePerPiece" name="selling_price_per_piece" class="form-control">
                                </div>
                            </div>
                        </div>

                        {{-- Inputs por kg --}}
                        <div id="kgInputs" class="d-none">
                            <div class="mb-3">
                                <label for="totalWeightKg" class="form-label fw-semibold">Peso total (kg)</label>
                                <input type="number" step="0.01" id="totalWeightKg" name="product_weight" class="form-control" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="pricePerKg" class="form-label fw-semibold">Precio de venta por kg (€)</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="number" step="0.01" id="pricePerKg" name="selling_price_per_kg" class="form-control">
                                </div>
                            </div>
                        </div>

                        {{-- Input adicional: salario del conductor --}}
                        <div class="mb-3">
                            <label for="driverSalary" class="form-label fw-semibold">Salario del conductor por minuto (€)</label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="number" step="0.01" id="driverSalary" name="driver_salary" class="form-control">
                            </div>
                        </div>

                        {{-- Visualización del margen potencial --}}
                        <div class="mb-3">
                            <label for="potentialMargin" class="form-label fw-semibold">Margen potencial</label>
                            <p id="potentialMargin" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Botón enviar --}}
        <div class="text-end">
            <button type="submit" class="btn btn-lg btn-primary">
                <i class="bi bi-save2 me-2"></i> Guardar proveedor externo
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Elementos de detalles de compra
    const purchaseCostPerKg = document.getElementById('purchaseCostPerKg');
    const totalWeightPurchased = document.getElementById('totalWeightPurchased');
    const totalPurchaseCost = document.getElementById('totalPurchaseCost');

    // Elementos de mano de obra y totales
    const laborTimeInput = document.getElementById('laborTimeInput');
    const costPerMin = parseFloat(document.getElementById('costPerMin').value) || 0;
    const laborCostIn = document.getElementById('laborCost');
    const packingCostIn = document.getElementById('packingCost');
    const totalExpenseIn = document.getElementById('totalExpense');
    const adjustedBreakEven = document.getElementById('adjustedBreakEven');
    const adjustedBreakEvenInput = document.getElementById('adjustedBreakEvenInput');
    const driverSalaryInp = document.getElementById('driverSalary');

    // Elementos del modo de venta
    const modePiece = document.getElementById('modePiece');
    the modeKg = document.getElementById('modeKg');
    const totalPiecesIn = document.getElementById('totalPieces');
    const pricePerPiece = document.getElementById('pricePerPiece');
    const pricePerKg = document.getElementById('pricePerKg');
    const totalWeightKg = document.getElementById('totalWeightKg');

    // Calcular costo de compra a partir del costo por kg y el peso total comprado
    function recalcPurchaseCost() {
        const costKg = parseFloat(purchaseCostPerKg.value) || 0;
        const weight = parseFloat(totalWeightPurchased.value) || 0;
        const purchaseCost = costKg * weight;
        totalPurchaseCost.value = purchaseCost.toFixed(2);
        totalWeightKg.value = weight.toFixed(2);
        recalcTotals();
    }

    // Calcular costo de mano de obra usando el tiempo activo de preparación y el costo por minuto
    function recalcLabor() {
        const mins = parseFloat(laborTimeInput.value || 0);
        const laborC = mins * costPerMin;
        laborCostIn.value = laborC.toFixed(2);
        recalcTotals();
    }

    // Recalcular gasto total: costo de compra + mano de obra + empaque
    function recalcTotals() {
        const purchaseCost = parseFloat(totalPurchaseCost.value) || 0;
        const laborCost = parseFloat(laborCostIn.value) || 0;
        const packCost = parseFloat(packingCostIn.value) || 0;
        const totalExp = purchaseCost + laborCost + packCost;
        totalExpenseIn.value = totalExp.toFixed(2);
        recalcAdjustedBreakEven();
        recalcMargin();
    }

    // Calcular punto de equilibrio ajustado: suma de mano de obra y empaque + salario del conductor * tiempo de mano de obra
    function recalcAdjustedBreakEven() {
        const laborCost = parseFloat(laborCostIn.value) || 0;
        const packCost = parseFloat(packingCostIn.value) || 0;
        const driverSalary = parseFloat(driverSalaryInp.value) || 0;
        const laborTime = parseFloat(laborTimeInput.value) || 0;
        const adjustedValue = (laborCost + packCost) + (driverSalary * laborTime);
        adjustedBreakEven.innerText = '€' + adjustedValue.toFixed(2);
        adjustedBreakEvenInput.value = adjustedValue.toFixed(2);
    }

    // Recalcular margen potencial según el modo de venta
    function recalcMargin() {
        const expTotal = parseFloat(totalExpenseIn.value) || 0;
        let margin, label;
        if (modePiece.checked) {
            const pcs = parseFloat(totalPiecesIn.value) || 1;
            const sellP = parseFloat(pricePerPiece.value) || 0;
            margin = sellP - (expTotal / pcs);
            label = ' / pieza';
        } else {
            const wt = parseFloat(totalWeightKg.value) || 1;
            const sellK = parseFloat(pricePerKg.value) || 0;
            margin = sellK - (expTotal * 1000 / wt);
            label = ' / kg';
        }
        document.getElementById('potentialMargin').innerText = '€' + margin.toFixed(2) + label;
    }

    // Eventos para detalles de compra
    purchaseCostPerKg.addEventListener('input', recalcPurchaseCost);
    totalWeightPurchased.addEventListener('input', recalcPurchaseCost);

    // Evento para mano de obra
    laborTimeInput.addEventListener('input', recalcLabor);

    // Evento para cambio en el costo de empaque
    packingCostIn.addEventListener('input', recalcTotals);

    // Evento para cambio en salario del conductor
    driverSalaryInp.addEventListener('input', recalcAdjustedBreakEven);

    // Alternar modo de venta
    function updateMode() {
        const pieceInputs = document.getElementById('pieceInputs');
        const kgInputs = document.getElementById('kgInputs');
        if (modePiece.checked) {
            pieceInputs.classList.remove('d-none');
            kgInputs.classList.add('d-none');
        } else {
            pieceInputs.classList.add('d-none');
            kgInputs.classList.remove('d-none');
        }
        recalcMargin();
    }
    modePiece.addEventListener('change', updateMode);
    modeKg.addEventListener('change', updateMode);

    // Eventos de precio de venta
    pricePerPiece.addEventListener('input', recalcMargin);
    pricePerKg.addEventListener('input', recalcMargin);

    // Inicializar cálculos
    updateMode();
    recalcPurchaseCost();
    recalcLabor();
});
</script>
@endsection
