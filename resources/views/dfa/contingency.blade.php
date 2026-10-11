<table class="dfa">
    <tr>
        <td class="emitter-cell">@include('efatura::dfa.partials.emitter')</td>
        <td class="facts-cell">
            <div><strong>Série:</strong> {{ $dfa->content->series }}</div>
            <div><strong>Tipo Documento:</strong> {{ $dfa->content->documentType }}</div>
            <div><strong>Nº Documento:</strong> {{ $dfa->content->documentNumber }}</div>
            <div><strong>Data:</strong> {{ $dfa->content->issuedAtLabel() }}</div>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="center contingency">
            <div class="contingency-title">EMITIDO EM CONTINGÊNCIA {{ $dfa->contingencyMode() }}</div>
            @if ($dfa->iuc !== null)
                <div class="contingency-title">IUC {{ $dfa->iuc }}</div>
            @endif
            <div class="pending">Pendente de Autorização</div>
        </td>
    </tr>
    <tr>
        <td colspan="2">@include('efatura::dfa.partials.items')</td>
    </tr>
    @if ($dfa->content->totals !== null)
        <tr>
            <td colspan="2" class="totals-cell">@include('efatura::dfa.partials.totals', ['totals' => $dfa->content->totals])</td>
        </tr>
    @endif
    @include('efatura::dfa.partials.conversion', ['colspan' => 2])
    <tr>
        <td colspan="2" class="center consult">Consulta DFE em <strong>efatura.cv</strong></td>
    </tr>
    <tr>
        <td colspan="2" class="verification-cell">
            <table class="verification">
                <tr>
                    @if ($qrCode !== null)
                        <td class="qr-cell"><img class="qr" src="{{ $qrCode }}" alt="QR Code"></td>
                    @endif
                    <td>@include('efatura::dfa.partials.customer')</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="deduction">Este documento não confere direito a dedução de imposto, enquanto o documento associado não for transmitido e autorizado pela Administração Fiscal.</td>
    </tr>
</table>
