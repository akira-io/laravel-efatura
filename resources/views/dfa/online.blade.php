<table class="dfa dfa-online">
    <tr>
        <td>@include('efatura::dfa.partials.emitter')</td>
    </tr>
    @if ($dfa->content->totals !== null)
        <tr>
            <td class="totals-cell">@include('efatura::dfa.partials.totals', ['totals' => $dfa->content->totals])</td>
        </tr>
    @endif
    @include('efatura::dfa.partials.conversion', ['colspan' => 1])
    <tr>
        <td class="center consult">Consulta DFE em <strong>efatura.cv</strong></td>
    </tr>
    <tr>
        <td class="center">
            <img class="qr" src="{{ $qrCode }}" alt="QR Code">
            <div class="iud">{{ $dfa->iud }}</div>
        </td>
    </tr>
</table>
