<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>DFA {{ $dfa->iud }}</title>
    @include('efatura::dfa.partials.styles')
</head>
<body>
@if ($dfa->pendingAuthorization())
    @include('efatura::dfa.contingency')
@else
    @include('efatura::dfa.online')
@endif
</body>
</html>
