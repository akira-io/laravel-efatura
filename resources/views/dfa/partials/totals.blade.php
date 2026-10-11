<table class="totals">
    <tr><td class="label">Nº de Itens:</td><td>{{ $totals->itemCount }}</td></tr>
    <tr><td class="label">Total Líquido:</td><td>{{ $totals->net }}</td></tr>
    <tr><td class="label">Encargos:</td><td>{{ $totals->charges }}</td></tr>
    <tr><td class="label">Descontos:</td><td>{{ $totals->discounts }}</td></tr>
    <tr><td class="label">Imposto:</td><td>{{ $totals->tax }}</td></tr>
    @if ($totals->withholding !== null)
        <tr><td class="label">Retenção na Fonte:</td><td>{{ $totals->withholding }}</td></tr>
    @endif
    @if ($totals->rounding !== null)
        <tr><td class="label">Arredondamento:</td><td>{{ $totals->rounding }}</td></tr>
    @endif
    <tr class="payable"><td class="label">Total a Pagar:</td><td>{{ $totals->payable }}</td></tr>
</table>
