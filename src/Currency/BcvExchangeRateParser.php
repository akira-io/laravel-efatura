<?php

declare(strict_types=1);

namespace Akira\Efatura\Currency;

use Akira\Efatura\Enums\ExchangeRateFailure;
use Akira\Efatura\Enums\ExchangeRateType;
use Akira\Efatura\Exceptions\ExchangeRateException;
use Akira\Efatura\Money\DecimalFormatter;
use Akira\Efatura\Support\Fiscal;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Str;

use const LIBXML_NOERROR;

final readonly class BcvExchangeRateParser
{
    private const array HEADERS = ['PAIS', 'MOEDA', 'UNIDADES', 'COMPRA', 'VENDA'];

    private const string HEADING = '/\ATaxas de Câmbio para o dia (\d{2})\/(\d{2})\/(\d{4})\z/iu';

    private const string DECIMAL = '/\A\d+(?:\.\d+)?\z/';

    public function parse(string $html, string $currency, ExchangeRateType $type): BcvRate
    {
        $tables = [];
        foreach (HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelectorAll('table') as $table) {
            $rows = $this->rows($table);
            foreach ($rows as $index => $cells) {
                $headers = array_map(fn (Element $cell): string => Str::upper(Str::ascii($this->text($cell))), $cells);
                if (collect(self::HEADERS)->every(static fn (string $header): bool => \count(array_keys($headers, $header, true)) === 1)) {
                    $tables[] = [$table, $rows, $index, array_flip($headers)];
                }
            }
        }

        if (\count($tables) !== 1) {
            throw self::invalid();
        }

        [$table, $rows, $headerIndex, $columns] = $tables[0];
        $column                                 = $type === ExchangeRateType::Sell ? 'VENDA' : 'COMPRA';

        return new BcvRate($this->publishedOn($table, $rows), $this->rate(\array_slice($rows, $headerIndex + 1), $columns, $currency, $column));
    }

    /**
     * @return list<list<Element>>
     */
    private function rows(Element $table): array
    {
        $rows = [];
        foreach ($table->querySelectorAll('tr') as $row) {
            if ($row->closest('table') === $table) {
                $rows[] = $this->cells($row);
            }
        }

        return $rows;
    }

    /**
     * @return list<Element>
     */
    private function cells(Element $row): array
    {
        $cells = [];
        foreach ($row->children as $cell) {
            if (\in_array($cell->localName, ['th', 'td'], true)) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    /**
     * @param list<list<Element>> $rows
     */
    private function publishedOn(Element $table, array $rows): CarbonImmutable
    {
        $headings = collect($rows)
            ->filter(static fn (array $cells): bool => \count($cells) === 1 && $cells[0]->localName === 'th' && $cells[0]->getAttribute('colspan') === '5')
            ->map(fn (array $cells): string => $this->text($cells[0]));
        $adjacent = $table->previousElementSibling;

        if ($adjacent instanceof Element && preg_match('/\Ah[1-6]\z/', $adjacent->localName) === 1) {
            $headings->push($this->text($adjacent));
        }

        $dates = $headings
            ->map(static fn (string $heading): array => preg_match(self::HEADING, $heading, $date) === 1 ? array_map(intval(...), \array_slice($date, 1)) : [])
            ->filter()
            ->values()
            ->all();

        if (\count($dates) !== 1) {
            throw self::invalid();
        }

        [$day, $month, $year] = $dates[0];

        if (! checkdate($month, $day, $year)) {
            throw self::invalid();
        }

        return new CarbonImmutable(implode('-', [$year, $month, $day]), Fiscal::TIMEZONE);
    }

    /**
     * @param list<list<Element>> $rows
     * @param array<string, int>  $columns
     */
    private function rate(array $rows, array $columns, string $currency, string $column): BigDecimal
    {
        $matches = array_values(array_filter(
            $rows,
            fn (array $cells): bool => Str::upper($this->text($cells[$columns['MOEDA']] ?? null)) === $currency,
        ));

        if ($matches === []) {
            throw ExchangeRateException::because(ExchangeRateFailure::CurrencyUnsupported);
        }

        if (\count($matches) > 1) {
            throw self::invalid();
        }

        $rate  = $this->decimal($matches[0][$columns[$column]] ?? null);
        $units = $this->decimal($matches[0][$columns['UNIDADES']] ?? null);

        return $rate->dividedBy($units, Fiscal::AMOUNT_SCALE, DecimalFormatter::fiscalRounding());
    }

    private function decimal(?Element $cell): BigDecimal
    {
        $unquoted = preg_replace('/\A([\'"])(.*)\1\z/s', '$2', $this->text($cell));
        $value    = Str::replaceFirst(',', '.', Str::remove(' ', (string) $unquoted));

        if (preg_match(self::DECIMAL, $value) !== 1 || BigDecimal::of($value)->isZero()) {
            throw ExchangeRateException::because(ExchangeRateFailure::RateInvalid);
        }

        return BigDecimal::of($value);
    }

    private function text(?Element $element): string
    {
        return Str::squish(Str::replace("\u{a0}", ' ', $element->textContent ?? ''));
    }

    private static function invalid(): ExchangeRateException
    {
        return ExchangeRateException::because(ExchangeRateFailure::ResponseInvalid);
    }
}
