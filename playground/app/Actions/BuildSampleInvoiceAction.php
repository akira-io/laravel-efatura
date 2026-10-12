<?php

declare(strict_types=1);

namespace App\Actions;

use Akira\Efatura\Actions\ResolveEmissionContextAction;
use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\ContingencyData;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\DocumentHeaderData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\EfaturaManager;
use Akira\Efatura\Enums\ContingencyReason;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\EmissionMode;
use App\Data\SampleInvoice;
use App\Data\SampleLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Psr\Clock\ClockInterface;

final readonly class BuildSampleInvoiceAction
{
    public function __construct(
        private EfaturaManager $efatura,
        private EfaturaConfig $config,
        private ResolveEmissionContextAction $resolveEmission,
        private ClockInterface $clock,
        private Repository $settings,
    ) {}

    public function handle(SampleInvoice $sample): DocumentData
    {
        $now = CarbonImmutable::instance($this->clock->now());
        $builder = $this->efatura->invoice()
            ->type(DocumentType::Invoice)
            ->header($this->header($now))
            ->receiver(PartyData::from(['taxId' => ['value' => '900800700', 'countryCode' => 'CV'], 'name' => 'Playground Receiver']))
            ->emission($this->emission($sample->mode, $now));

        $extensions = BigDecimal::zero();
        $taxes = BigDecimal::zero();

        foreach ($sample->lines as $index => $line) {
            $extension = $this->extension($line);
            $tax = $this->tax($extension, $sample->taxPercentage);
            $extensions = $extensions->plus($extension);
            $taxes = $taxes->plus($tax);
            $builder->line($this->line($index, $line, $extension, $sample->taxPercentage));
        }

        return $builder->totals(TotalsData::from([
            'priceExtensionTotalAmount' => (string) $extensions,
            'netTotalAmount' => (string) $extensions,
            'taxTotalAmount' => (string) $taxes,
            'payableAmount' => $sample->payableOverride ?? (string) $extensions->plus($taxes),
        ]))->build();
    }

    private function header(CarbonImmutable $now): DocumentHeaderData
    {
        return DocumentHeaderData::from([
            'issueDate' => $now,
            'issueTime' => $now,
            'ledCode' => $this->config->emitter?->led ?? 1,
            'serie' => $this->settings->string('playground.series'),
        ]);
    }

    private function emission(EmissionMode $mode, CarbonImmutable $now): EmissionContextData
    {
        $online = $this->resolveEmission->handle(null);

        if ($mode === EmissionMode::Online) {
            return $online;
        }

        $contingency = ContingencyData::from(Arr::whereNotNull([
            'issueDate' => $now,
            'issueTime' => $now,
            'ledCode' => $this->config->emitter?->led ?? 1,
            'reasonTypeCode' => $mode === EmissionMode::Off ? ContingencyReason::PowerFailure->value : ContingencyReason::InternetUnavailable->value,
            'iuc' => $mode === EmissionMode::Off ? $now->format('Y').'/1' : null,
        ]));

        return new EmissionContextData($mode, $contingency, $online->transmitterTaxId, $online->software);
    }

    private function line(int $index, SampleLine $line, BigDecimal $extension, string $taxPercentage): LineItemData
    {
        return LineItemData::from([
            'quantity' => ['value' => $line->quantity, 'unitCode' => 'C62'],
            'item' => ['description' => $line->description, 'emitterIdentification' => 'SKU-'.($index + 1)],
            'price' => $line->unitPrice,
            'priceExtension' => (string) $extension,
            'netTotal' => (string) $extension,
            'taxes' => [$taxPercentage === '0'
                ? ['taxTypeCode' => 'NA', 'taxExemptionReasonCode' => '1']
                : ['taxTypeCode' => 'IVA', 'taxPercentage' => $taxPercentage]],
        ]);
    }

    private function extension(SampleLine $line): BigDecimal
    {
        return BigDecimal::of($line->quantity)->multipliedBy($line->unitPrice)->toScale(2, RoundingMode::HALF_UP);
    }

    private function tax(BigDecimal $extension, string $taxPercentage): BigDecimal
    {
        return $extension->multipliedBy($taxPercentage)->dividedBy(100, 2, RoundingMode::HALF_UP);
    }
}
