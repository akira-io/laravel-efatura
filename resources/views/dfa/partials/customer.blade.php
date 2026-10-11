<table class="customer">
    <tr>
        <td class="customer-title"><strong>Cliente</strong></td>
        <td>
            @if ($dfa->fillsCustomerByHand())
                <div>NIF: ________________________________________</div>
                <div>Nome: ______________________________________</div>
                <div>Endereço: ___________________________________</div>
                <div>Contacto: ___________________________________</div>
            @else
                <div>NIF: {{ $dfa->content->receiver?->taxId }}</div>
                <div>Nome: {{ $dfa->content->receiver?->name }}</div>
                <div>Endereço: {{ $dfa->content->receiver?->address }}</div>
                <div>Contacto: {{ $dfa->content->receiver?->contact }}</div>
            @endif
        </td>
    </tr>
</table>
