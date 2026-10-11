<?php

declare(strict_types=1);

use Akira\Efatura\Currency\BcvExchangeRateParser;
use Akira\Efatura\Currency\BcvRate;
use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Tests\Support\CurrencyFixtures as C;

const BCV_HEADING      = '<tr><th colspan="5">Taxas de Câmbio para o dia 21/07/2026</th></tr>';
const BCV_HEADER_ROW   = '<tr><th>País</th><th>Moeda</th><th>Unidades</th><th>Compra</th><th>Venda</th></tr>';
const BCV_EUR_ROW      = '<tr><td>Zona Euro</td><td>EUR</td><td>1</td><td>110,26500</td><td>110,26500</td></tr>';
const BCV_TABLE_OPENER = '<table class="table table-striped table-bordered">';

function bcvPage(array $replacements = []): string
{
    return strtr(C::fixture('bcv-rates-2026-07-21.html'), $replacements);
}

function bcvRate(string $html, string $currency = 'EUR', ExchangeRateType $type = ExchangeRateType::Buy): BcvRate
{
    return new BcvExchangeRateParser()->parse($html, $currency, $type);
}

it('reads the publication day and the rate of the official table', function (): void {
    $rate = bcvRate(bcvPage());

    expect($rate->publishedOn->toIso8601String())->toBe('2026-07-21T00:00:00-01:00')
        ->and((string) $rate->rate)->toBe('110.26500');
});

it('accepts the shapes the BCV page has used', function (string $html, string $currency, string $expected): void {
    expect((string) bcvRate($html, $currency)->rate)->toBe($expected);
})->with([
    'an adjacent heading'    => [fn (): string => bcvPage([BCV_HEADING => '', BCV_TABLE_OPENER => '<h2>Taxas de Câmbio para o dia 21/07/2026</h2>' . BCV_TABLE_OPENER]), 'EUR', '110.26500'],
    'unrelated dated tables' => [fn (): string => bcvPage([
        '<main>'  => '<main><table><tr><th colspan="5">Taxas de Câmbio para o dia 20/07/2026</th></tr></table>',
        '</main>' => '<section><h3>Taxas de Câmbio para o dia 20/07/2026</h3></section></main>',
    ]), 'EUR', '110.26500'],
    'unaccented upper headers'    => [fn (): string => bcvPage([BCV_HEADER_ROW => '<tr><th>PAIS</th><th>MOEDA</th><th>UNIDADES</th><th>COMPRA</th><th>VENDA</th></tr>']), 'EUR', '110.26500'],
    'spaces in the numbers'       => [fn (): string => bcvPage(['<td>110,26500</td><td>110,26500</td>' => "<td>1 10,265 00</td><td>1\u{a0}10,265\u{a0}00</td>"]), 'EUR', '110.26500'],
    'single quoted units'         => [fn (): string => bcvPage(['<td>100</td>' => '<td>&#39;100&#39;</td>']), 'JPY', '0.59727'],
    'double quoted units'         => [fn (): string => bcvPage(['<td>100</td>' => '<td>"100"</td>']), 'JPY', '0.59727'],
    'a lower case currency'       => [fn (): string => bcvPage(['<td>EUR</td>' => '<td> eur </td>']), 'EUR', '110.26500'],
    'a latin-1 page that says so' => [fn (): string => mb_convert_encoding('<meta charset="iso-8859-1">' . bcvPage(), 'ISO-8859-1', 'UTF-8'), 'EUR', '110.26500'],
    'a row of an unknown code'    => [fn (): string => bcvPage([BCV_EUR_ROW => BCV_EUR_ROW . '<tr><td>X</td><td>???</td><td>1</td><td>1</td><td>1</td></tr>']), 'EUR', '110.26500'],
    'a half unit rounded up'      => [fn (): string => bcvPage(['<td>59,72700</td>' => '<td>59,72750</td>']), 'JPY', '0.59728'],
    'a third of a unit'           => [fn (): string => bcvPage(['<td>100</td><td>59,72700</td>' => '<td>3</td><td>1,00000</td>']), 'JPY', '0.33333'],
]);

it('refuses a page it cannot read with certainty', function (string $html): void {
    expect(C::failure(fn (): BcvRate => bcvRate($html)))->toBe(ExchangeRateFailure::ResponseInvalid);
})->with([
    'an empty table'                => ['<main><table></table></main>'],
    'an html error page'            => ['<html><body><h1>Erro 500</h1><p>Serviço indisponível</p></body></html>'],
    'not html at all'               => ["\xff\xfe\0garbage<<<>>>"],
    'a missing column'              => [fn (): string => bcvPage(['<th>Venda</th>' => '<th>Outro</th>'])],
    'a duplicated column'           => [fn (): string => bcvPage(['<th>Moeda</th>' => '<th>Moeda</th><th>Moeda</th>'])],
    'two rate tables'               => [fn (): string => bcvPage(['</main>' => BCV_TABLE_OPENER . BCV_HEADING . BCV_HEADER_ROW . BCV_EUR_ROW . '</table></main>'])],
    'two header rows'               => [fn (): string => bcvPage([BCV_HEADER_ROW => BCV_HEADER_ROW . BCV_HEADER_ROW])],
    'a suffixed heading'            => [fn (): string => bcvPage(['21/07/2026</th>' => '21/07/2026 - informação provisória</th>'])],
    'two headings'                  => [fn (): string => bcvPage([BCV_HEADING => BCV_HEADING . BCV_HEADING])],
    'a heading and an adjacent one' => [fn (): string => bcvPage([BCV_TABLE_OPENER => '<h2>Taxas de Câmbio para o dia 21/07/2026</h2>' . BCV_TABLE_OPENER])],
    'no heading'                    => [fn (): string => bcvPage([BCV_HEADING => ''])],
    'a heading of another span'     => [fn (): string => bcvPage(['colspan="5"' => 'colspan="4"'])],
    'a heading in a data cell'      => [fn (): string => bcvPage(['<th colspan="5">Taxas de Câmbio para o dia 21/07/2026</th>' => '<td colspan="5">Taxas de Câmbio para o dia 21/07/2026</td>'])],
    'an adjacent paragraph only'    => [fn (): string => bcvPage([BCV_HEADING => '', BCV_TABLE_OPENER => '<p>Taxas de Câmbio para o dia 21/07/2026</p>' . BCV_TABLE_OPENER])],
    'an undated adjacent heading'   => [fn (): string => bcvPage([BCV_HEADING => '', BCV_TABLE_OPENER => '<h2>Taxas de Câmbio</h2>' . BCV_TABLE_OPENER])],
    'an impossible date'            => [fn (): string => bcvPage(['21/07/2026' => '31/02/2026'])],
    'a duplicated currency'         => [fn (): string => bcvPage([BCV_EUR_ROW => BCV_EUR_ROW . BCV_EUR_ROW])],
    'a latin-1 page that does not'  => [fn (): string => mb_convert_encoding(bcvPage(), 'ISO-8859-1', 'UTF-8')],
]);

it('refuses a currency the BCV did not publish', function (): void {
    expect(C::failure(fn (): BcvRate => bcvRate(bcvPage(), 'USD')))->toBe(ExchangeRateFailure::CurrencyUnsupported);
});

it('refuses rates and units that are not positive decimals without keeping them', function (array $replacements, string $currency): void {
    $exception = C::exception(fn (): BcvRate => bcvRate(bcvPage($replacements), $currency));

    expect($exception->failure)->toBe(ExchangeRateFailure::RateInvalid)
        ->and($exception->context)->toBe([])
        ->and($exception->getMessage())->not->toContain('SECRET');
})->with([
    'zero units'          => [['<td>1</td><td>110,26500</td>' => '<td>0</td><td>110,26500</td>'], 'EUR'],
    'worded units'        => [['<td>1</td><td>110,26500</td>' => '<td>one</td><td>110,26500</td>'], 'EUR'],
    'a secret rate'       => [['<td>110,26500</td>' => '<td>SECRET</td>'], 'EUR'],
    'a zero rate'         => [['<td>110,26500</td>' => '<td>0,00000</td>'], 'EUR'],
    'an empty rate'       => [['<td>110,26500</td>' => '<td></td>'], 'EUR'],
    'a negative rate'     => [['<td>110,26500</td>' => '<td>-110,26500</td>'], 'EUR'],
    'two decimal commas'  => [['<td>110,26500</td>' => '<td>110,265,00</td>'], 'EUR'],
    'mismatched quotes'   => [['<td>100</td>' => '<td>&#39;100"</td>'], 'JPY'],
    'an inner quote'      => [['<td>100</td>' => '<td>1&#39;00</td>'], 'JPY'],
    'a missing rate cell' => [['<td>Zona Euro</td><td>EUR</td><td>1</td><td>110,26500</td><td>110,26500</td>' => '<td>Zona Euro</td><td>EUR</td><td>1</td>'], 'EUR'],
]);

function bcvPageWith(int $tables, int $rows): string
{
    $filler = str_repeat('<table>' . str_repeat('<tr><td>x</td></tr>', $rows) . '</table>', $tables);

    return bcvPage(['</main>' => $filler . '</main>']);
}

it('reads a page at the structure limits', function (int $tables, int $rows): void {
    expect((string) bcvRate(bcvPageWith($tables, $rows))->rate)->toBe('110.26500');
})->with([
    'twenty tables'     => [19, 0],
    'five hundred rows' => [1, 496],
]);

it('refuses a page above the structure limits before reading its tables', function (int $tables, int $rows): void {
    expect(C::failure(fn (): BcvRate => bcvRate(bcvPageWith($tables, $rows))))->toBe(ExchangeRateFailure::ResponseInvalid);
})->with([
    'twenty one tables'         => [20, 0],
    'five hundred and one rows' => [1, 497],
]);

it('reads only the rows of the rate table itself, not those of a table nested in it', function (): void {
    $nested = '<tr><td colspan="5"><table><tr><td>Zona Euro</td><td>EUR</td><td>1</td><td>1,00000</td><td>1,00000</td></tr></table></td></tr>';

    expect((string) bcvRate(bcvPage([BCV_EUR_ROW => BCV_EUR_ROW . $nested]))->rate)->toBe('110.26500');
});
