@extends('layouts.app', ['title' => 'Document'])

@section('content')
    @include('partials.invoice-form', ['action' => route('document.preview'), 'submit' => 'Build the XML'])
    @isset($preview)
        <section>
            <h2>Unsigned XML</h2>
            <dl>
                <dt>IUD</dt><dd>{{ $preview->iud }}</dd>
                <dt>Document number</dt><dd>{{ $preview->document->header->series }} {{ $preview->document->header->documentNumber }}</dd>
                <dt>Amount to pay</dt><dd>{{ \App\Support\Amount::format($preview->document->totals->payableAmount) }}</dd>
                <dt>XSD</dt>
                <dd>
                    @if ($preview->schemaValid())
                        <span class="ok">valid</span>
                    @else
                        <span class="bad">invalid</span>
                        <ul>@foreach ($preview->violations as $violation)<li>{{ $violation }}</li>@endforeach</ul>
                    @endif
                </dd>
            </dl>
            <pre>{{ \App\Support\PrettyXml::format($preview->xml) }}</pre>
        </section>
    @endisset
@endsection
