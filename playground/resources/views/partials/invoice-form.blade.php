<form class="card" method="POST" action="{{ $action }}" @isset($target) target="{{ $target }}" @endisset>
    @csrf
    <p class="muted">{{ $hint ?? 'A sample invoice (FTE) from the configured emitter to a fixed receiver. Leave a line blank to drop it.' }}</p>
    <table>
        <thead><tr><th>Description</th><th>Quantity</th><th>Unit price{{ isset($currency) ? ' (' . $currency . ')' : '' }}</th></tr></thead>
        <tbody>
        @foreach ($formLines as $index => $line)
            <tr>
                <td><input name="lines[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" aria-label="Description of line {{ $index + 1 }}"></td>
                <td><input name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" inputmode="decimal" aria-label="Quantity of line {{ $index + 1 }}"></td>
                <td><input name="lines[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}" inputmode="decimal" aria-label="Unit price of line {{ $index + 1 }}"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="grid">
        <label>VAT (IVA)
            <select name="tax_percentage">
                @foreach (['15' => '15%', '8' => '8%', '0' => 'Exempt'] as $value => $label)
                    <option value="{{ $value }}" @selected($formTax === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Override the amount to pay (leave blank to compute it)
            <input name="payable_override" value="{{ $formPayableOverride }}" inputmode="decimal">
        </label>
        @isset($extra) @include($extra) @endisset
    </div>
    <p><button type="submit">{{ $submit }}</button></p>
</form>
