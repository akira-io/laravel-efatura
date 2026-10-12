@extends('layouts.app', ['title' => 'Currency'])

@section('content')
    @include('partials.invoice-form', [
        'action' => route('currency.convert'),
        'submit' => 'Convert to CVE',
        'currency' => 'EUR',
        'hint' => 'The prices are read as EUR and converted with the configured exchange-rate provider (efatura.exchange_rates.provider).',
    ])
    @isset($conversion)
        <section>
            <h2>Converted document</h2>
            <table>
                <thead><tr><th>Total</th><th>Source ({{ $conversion->sourceCurrency }})</th><th>Converted (CVE)</th></tr></thead>
                <tbody>
                @foreach (['priceExtensionTotalAmount' => 'Price extension', 'netTotalAmount' => 'Net total', 'taxTotalAmount' => 'Taxes', 'payableRoundingAmount' => 'Rounding', 'payableAmount' => 'Amount to pay'] as $total => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ \App\Support\Amount::format($conversion->source->totals->{$total}, $conversion->sourceCurrency) }}</td>
                        <td>{{ \App\Support\Amount::format($conversion->converted->totals->{$total}) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <h3>PayableAlternativeAmount</h3>
            <dl>
                @foreach ($conversion->alternatives as $alternative)
                    <dt>{{ $alternative->currencyCode }}</dt>
                    <dd>{{ \App\Support\Amount::format($alternative->value, $alternative->currencyCode) }} at 1 {{ $alternative->currencyCode }} = {{ $alternative->exchangeRate }} CVE ({{ $conversion->provider }})</dd>
                @endforeach
            </dl>
        </section>
    @endisset
@endsection
