<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDfaDataAction;
use Akira\Efatura\Contracts\DfaRenderer;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Tests\Support\DfaFixtures as D;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;

function pdfContentStreams(string $pdf): array
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);

    return array_values(array_filter(array_map(fn (string $stream): string|false => @gzuncompress($stream), $matches[1])));
}

it('renders every line of a contingency DFA of three hundred lines inside the pages', function (): void {
    $lines = array_map(
        fn (int $number): LineItemData => LineItemData::from(F::linePayload(['item' => ['description' => 'Product ' . $number, 'emitterIdentification' => 'SKU' . $number]])),
        range(1, 300),
    );
    $document = new ElectronicInvoiceData(...[...get_object_vars(D::offline()), 'lines' => $lines]);

    $pdf     = resolve(DfaRenderer::class)->render(resolve(BuildDfaDataAction::class)->handle(D::prepared($document)))->bytes;
    $content = implode("\n", pdfContentStreams($pdf));
    $lastSku = mb_convert_encoding('SKU300', 'UTF-16BE', 'UTF-8');

    expect($pdf)->toStartWith('%PDF-')
        ->toEndWith("%%EOF\n")
        ->and(preg_match('/\/Type \/Pages\n\/Kids \[[^\]]*\]\n\/Count (\d+)/', $pdf, $pages))->toBe(1)
        ->and((int) $pages[1])->toBeGreaterThan(5)
        ->and($content)->toContain($lastSku)
        ->and(preg_match('/BT [\d.]+ -[\d.]+ Td/', $content))->toBe(0);
});
