<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} | e-Fatura Playground</title>
    <link rel="stylesheet" href="{{ asset('playground.css') }}">
</head>
<body>
<header>
    <a class="brand" href="{{ route('dashboard') }}">e-Fatura Playground</a>
    <nav>
        <a href="{{ route('document') }}">Document</a>
        <a href="{{ route('sign') }}">Sign</a>
        <a href="{{ route('dfa') }}">DFA</a>
        <a href="{{ route('currency') }}">Currency</a>
        <a href="{{ route('doctor') }}">Doctor</a>
        <a href="{{ route('issue') }}">Issue</a>
    </nav>
    <span class="badge">{{ config('efatura.environment') }} repository</span>
</header>
<main>
    <h1>{{ $title ?? 'Dashboard' }}</h1>
    @if ($errors->any())
        <section class="errors" role="alert">
            <h2>The package refused the input</h2>
            <ul>
                @foreach ($errors->getMessages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li><code>{{ $field }}</code> {{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        </section>
    @endif
    @yield('content')
</main>
</body>
</html>
