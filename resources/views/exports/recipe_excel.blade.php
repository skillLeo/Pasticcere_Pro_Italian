<table>

    {{-- ===== TABLE 1: KPI & Info ===== --}}

    {{-- Title --}}
    <tr>
        <td colspan="4" style="font-weight:bold;font-size:15px;background:#041930;color:#e2ae76;text-align:center;height:28px;">
            Ricetta: {{ $recipe->recipe_name }}
        </td>
    </tr>

    <tr><td colspan="4"></td></tr>

    {{-- Info rows --}}
    <tr>
        <td style="font-weight:bold;background:#f0f0f0;">Modalità vendita</td>
        <td>{{ $recipe->sell_mode === 'piece' ? 'Per pezzo' : 'Per kg' }}</td>
        <td style="font-weight:bold;background:#f0f0f0;">Moltiplicatore</td>
        <td>{{ number_format($multiplier, 2, '.', '') }}</td>
    </tr>
    <tr>
        <td style="font-weight:bold;background:#f0f0f0;">Prezzo lordo</td>
        <td>€{{ number_format($grossPrice, 2, '.', '') }}</td>
        <td style="font-weight:bold;background:#f0f0f0;">Prezzo netto</td>
        <td>€{{ number_format($netPrice, 2, '.', '') }}</td>
    </tr>
    <tr>
        <td style="font-weight:bold;background:#f0f0f0;">IVA %</td>
        <td>{{ number_format($vatPct, 2, '.', '') }}%</td>
        <td></td>
        <td></td>
    </tr>

    <tr><td colspan="4"></td></tr>

    {{-- KPI Table Header --}}
    <tr>
        <td colspan="4" style="font-weight:bold;background:#041930;color:#e2ae76;text-align:center;">
            KPI
        </td>
    </tr>
    <tr style="font-weight:bold;background:#e2ae76;color:#041930;text-align:center;">
        <td>Voce</td>
        <td>Valore (€)</td>
        <td>% sul prezzo</td>
        <td></td>
    </tr>

    {{-- KPI Rows --}}
    <tr>
        <td>Prezzo</td>
        <td>€{{ number_format($grossPrice, 2, '.', '') }}</td>
        <td>100.00%</td>
        <td></td>
    </tr>
    <tr>
        <td>Costo ingredienti</td>
        <td>€{{ number_format($unitIngCost, 2, '.', '') }}</td>
        <td>{{ number_format($ingPct, 2, '.', '') }}%</td>
        <td></td>
    </tr>
    <tr>
        <td>Costo lavoro</td>
        <td>€{{ number_format($adjustedUnitLabCost, 2, '.', '') }}</td>
        <td>{{ number_format($labPct, 2, '.', '') }}%</td>
        <td></td>
    </tr>
    <tr>
        <td>Costo totale</td>
        <td>€{{ number_format($adjustedUnitTotalCost, 2, '.', '') }}</td>
        <td>{{ number_format($totalPct, 2, '.', '') }}%</td>
        <td></td>
    </tr>
    <tr style="font-weight:bold;background:#d1e7dd;">
        <td>Margine</td>
        <td>€{{ number_format($unitMargin, 2, '.', '') }}</td>
        <td>{{ number_format($unitMarginPct, 2, '.', '') }}%</td>
        <td></td>
    </tr>

    {{-- Spacer between tables --}}
    <tr><td colspan="4"></td></tr>
    <tr><td colspan="4"></td></tr>

    {{-- ===== TABLE 2: Ingredients ===== --}}

    {{-- Ingredients Header --}}
    <tr>
        <td colspan="4" style="font-weight:bold;background:#041930;color:#e2ae76;text-align:center;">
            Dettaglio ingredienti  (moltiplicatore: {{ number_format($multiplier, 2, '.', '') }})
        </td>
    </tr>
    <tr style="font-weight:bold;background:#e2ae76;color:#041930;text-align:center;">
        <td>Ingrediente</td>
        <td>Qtà (g)</td>
        <td>Costo (€)</td>
        <td></td>
    </tr>

    {{-- Ingredient rows --}}
    @foreach($ingredientRows as $row)
    <tr>
        <td>{{ $row['name'] }}</td>
        <td>{{ number_format($row['qty_g'], 0, '.', '') }}</td>
        <td>€{{ number_format($row['cost'], 2, '.', '') }}</td>
        <td></td>
    </tr>
    @endforeach

    {{-- Totals --}}
    <tr style="font-weight:bold;background:#fff3cd;">
        <td>Totale ingredienti</td>
        <td>{{ number_format($totalQty, 0, '.', '') }}</td>
        <td>€{{ number_format($totalIngCost, 2, '.', '') }}</td>
        <td></td>
    </tr>
    <tr style="font-weight:bold;">
        <td>Totale manodopera</td>
        <td></td>
        <td>€{{ number_format($adjustedBatchLabCost, 2, '.', '') }}</td>
        <td></td>
    </tr>
    <tr style="font-weight:bold;background:#d1e7dd;">
        <td>Totale finale</td>
        <td></td>
        <td>€{{ number_format($grandTotal, 2, '.', '') }}</td>
        <td></td>
    </tr>

</table>