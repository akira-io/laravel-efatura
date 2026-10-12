@extends('layouts.app', ['title' => 'DFA'])

@section('content')
    @include('partials.invoice-form', [
        'action' => route('dfa.render'),
        'submit' => 'Render the PDF',
        'target' => '_blank',
        'extra' => 'partials.dfa-options',
        'hint' => 'ONLINE issues the document through the configured transport first; OFFLINE and OFF only prepare it in contingency. The PDF opens in a new tab.',
    ])
@endsection
