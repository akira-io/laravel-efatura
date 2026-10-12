@extends('layouts.app', ['title' => 'Sign'])

@section('content')
    @include('partials.invoice-form', ['action' => route('sign.run'), 'submit' => 'Number, sign and package'])
    @isset($signed)
        <section>
            <h2>Signature</h2>
            <dl>
                <dt>IUD</dt><dd>{{ $signed->prepared->iud }}</dd>
                <dt>Signature</dt>
                <dd>
                    <span class="{{ $signed->signature->valid ? 'ok' : 'bad' }}">{{ $signed->signature->valid ? 'valid' : 'invalid' }}</span>
                    @foreach ($signed->signature->failures as $failure) <code>{{ $failure->code() }}</code> @endforeach
                </dd>
                <dt>Profile</dt><dd>{{ $signed->signature->profile?->name }}</dd>
                <dt>Certificate</dt><dd>{{ $signed->signature->certificateSubject }} (serial {{ $signed->signature->certificateSerialNumber }})</dd>
                <dt>Signing time</dt><dd>{{ $signed->prepared->signed->signingTime->format('Y-m-d H:i:s') }}</dd>
                <dt>XSD and signature check</dt><dd><span class="{{ $signed->validation->valid() ? 'ok' : 'bad' }}">{{ $signed->validation->valid() ? 'valid' : 'invalid' }}</span> {{ $signed->validation->schemaCode }}</dd>
                <dt>Archive</dt><dd>{{ implode(', ', $signed->prepared->archive->identifiers()) }} ({{ strlen($signed->prepared->archive->bytes) }} bytes)</dd>
            </dl>
            <p class="muted">The certificate is self-signed for local testing. Its chain is not verified against ICP-CV.</p>
            <pre>{{ \App\Support\PrettyXml::format($signed->prepared->signed->xml) }}</pre>
        </section>
    @endisset
@endsection
