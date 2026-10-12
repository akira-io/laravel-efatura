@extends('layouts.app', ['title' => 'Issue'])

@section('content')
    <p class="muted">Transport: {{ \App\Enums\PlaygroundTransport::configured()->label() }}.</p>
    @include('partials.invoice-form', ['action' => route('issue.run'), 'submit' => 'Issue the document'])
    @isset($issued)
        <section>
            <h2>Platform answer</h2>
            <dl>
                <dt>Outcome</dt><dd><span class="{{ $issued->authorized() ? 'ok' : 'bad' }}">{{ $issued->authorized() ? 'authorized' : 'rejected' }}</span></dd>
                <dt>IUD</dt><dd>{{ $issued->prepared->iud }}</dd>
                <dt>Authorization code</dt><dd>{{ $issued->result->authorizationCode ?? '-' }}</dd>
                <dt>Signature time stamp</dt><dd>{{ $issued->result->signatureTimeStamp ?? '-' }}</dd>
                <dt>HTTP status</dt><dd>{{ $issued->submission->status }}</dd>
                <dt>Received at</dt><dd>{{ $issued->submission->receivedAt->format('Y-m-d H:i:s') }}</dd>
            </dl>
            @if ($issued->result->messages !== [])
                <h3>Messages</h3>
                <ul>
                    @foreach ($issued->result->messages as $message)
                        <li><code>{{ $message->code }}</code> {{ $message->description }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endisset
@endsection
