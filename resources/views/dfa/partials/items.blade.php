<table class="items">
    <thead>
        <tr>
            @if ($dfa->showsItemCodes())
                <th class="left">Código</th>
            @endif
            <th class="left">Descrição</th>
            <th class="right">Quantidade</th>
            <th class="center">Unidade</th>
            <th class="right">Valor Unitário</th>
            <th class="center">Taxa Imposto</th>
            <th class="right">Valor Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($dfa->content->lines as $line)
            <tr>
                @if ($dfa->showsItemCodes())
                    <td>{{ $line->code }}</td>
                @endif
                <td>{{ $line->description }}</td>
                <td class="right">{{ $line->quantity }}</td>
                <td class="center">{{ $line->unit }}</td>
                <td class="right">{{ $line->unitPrice }}</td>
                <td class="center">{{ $line->tax }}</td>
                <td class="right">{{ $line->total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
