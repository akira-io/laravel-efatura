@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    <section>
        <p>Every page runs the local <code>akira/efatura</code> code through its public actions. Nothing leaves this machine unless you run the remote doctor checks or switch the transport and configure real credentials.</p>
        <dl>
            <dt>Repository</dt><dd>{{ config('efatura.environment') }}</dd>
            <dt>Transport</dt><dd>{{ \App\Enums\PlaygroundTransport::configured()->label() }}</dd>
            <dt>Exchange rates</dt><dd>{{ config('efatura.exchange_rates.provider') }}</dd>
            <dt>Signing certificate</dt><dd>{{ config('efatura.certificates.disk') }}:{{ config('efatura.certificates.certificate_path') }} (self-signed, test only)</dd>
        </dl>
    </section>
    <div class="grid">
        <section><h2><a href="{{ route('document') }}">Document</a></h2><p>Build an FTE with the fluent builder, number it and write the unsigned XML checked against the XSD.</p></section>
        <section><h2><a href="{{ route('sign') }}">Sign</a></h2><p>Number, sign with the test certificate and package the document, then verify the XAdES-BES signature.</p></section>
        <section><h2><a href="{{ route('dfa') }}">DFA</a></h2><p>Render the ONLINE, OFFLINE or OFF Documento Fiscal Auxiliar as a PDF.</p></section>
        <section><h2><a href="{{ route('currency') }}">Currency</a></h2><p>Convert a document written in EUR to CVE and keep the PayableAlternativeAmount.</p></section>
        <section><h2><a href="{{ route('doctor') }}">Doctor</a></h2><p>Run the readiness checks locally, or against the configured endpoint on request.</p></section>
        <section><h2><a href="{{ route('issue') }}">Issue</a></h2><p>Prepare, submit and read the authorization answer of the configured transport.</p></section>
    </div>
@endsection
