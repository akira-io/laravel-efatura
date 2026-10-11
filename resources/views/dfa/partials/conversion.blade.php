@if ($dfa->content->conversions !== [])
    <tr>
        <td colspan="{{ $colspan }}" class="conversion">
            <div class="conversion-title">Conversão cambial</div>
            <table class="conversion-table">
                <tr><th class="left">Moeda</th><th class="right">Montante</th><th class="right">Câmbio</th></tr>
                @foreach ($dfa->content->conversions as $conversion)
                    <tr>
                        <td>{{ $conversion->currency }}</td>
                        <td class="right">{{ $conversion->amount }}</td>
                        <td class="right">{{ $conversion->rate }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
@endif
