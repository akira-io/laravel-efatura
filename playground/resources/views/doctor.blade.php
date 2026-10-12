@extends('layouts.app', ['title' => 'Doctor'])

@section('content')
    <section>
        <p>
            <span class="{{ $report->ready() ? 'ok' : 'bad' }}">{{ $report->ready() ? 'Ready to issue' : 'Not ready' }}</span>
            <span class="muted">{{ $remote ? 'Local and remote checks.' : 'Local checks only: nothing was sent over the network.' }}</span>
        </p>
        <table>
            <thead><tr><th>Check</th><th>Status</th><th>Code</th><th>Field</th><th>Required</th></tr></thead>
            <tbody>
            @foreach ($report->results as $result)
                <tr>
                    <td>{{ $result->check->value }}</td>
                    <td class="{{ \App\Support\StatusStyle::of($result->status) }}">{{ $result->status->value }}</td>
                    <td><code>{{ $result->code }}</code></td>
                    <td>{{ $result->field ?? '' }}</td>
                    <td>{{ $result->required ? 'yes' : 'no' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <form class="card" method="POST" action="{{ route('doctor.remote') }}">
        @csrf
        <p>The remote checks reach the base URL of the configured driver ({{ config('efatura.driver') }}) and compare the clocks.</p>
        <button type="submit">Run the remote checks</button>
    </form>
@endsection
