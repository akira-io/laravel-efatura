<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDfaDataAction;
use Akira\Efatura\Contracts\DfaRenderer;
use Akira\Efatura\Dfa\DfaView;
use Akira\Efatura\Dfa\DompdfDfaRenderer;
use Akira\Efatura\Dfa\LibraryRequirement;
use Akira\Efatura\Dfa\RenderedDfa;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\DfaException;
use Akira\Efatura\Tests\Support\DfaFixtures as D;

it('renders each DFA model as an A4 PDF named after the IUD', function (Closure $source, EmissionMode $model): void {
    $renderer = resolve(DfaRenderer::class);
    $rendered = $renderer->render(resolve(BuildDfaDataAction::class)->handle($source()));

    expect($renderer)->toBeInstanceOf(DompdfDfaRenderer::class)
        ->and($rendered->bytes)->toStartWith('%PDF-')
        ->toContain('/MediaBox [0.000 0.000 595.280 841.890]')
        ->toContain('/CreationDate (D:20261002130000Z)')
        ->toContain('/ModDate (D:20261002130000Z)')
        ->and($rendered->filename)->toBe(D::iud() . '.pdf')
        ->and($rendered->iud)->toBe(D::iud())
        ->and($rendered->model)->toBe($model)
        ->and($rendered->contentType)->toBe('application/pdf');
})->with([
    'online'  => [fn (): object => D::issued(), EmissionMode::Online],
    'offline' => [fn (): object => D::prepared(D::offline()), EmissionMode::Offline],
    'off'     => [fn (): object => D::prepared(D::off()), EmissionMode::Off],
]);

it('prints the same PDF, byte for byte, when the same document is reprinted', function (): void {
    $dfa = resolve(BuildDfaDataAction::class)->handle(D::issued());

    expect(resolve(DfaRenderer::class)->render($dfa))->toEqual(resolve(DfaRenderer::class)->render(resolve(BuildDfaDataAction::class)->handle(D::issued())));
});

it('fails with a stable code when dompdf is not installed', function (): void {
    $renderer = new DompdfDfaRenderer(resolve(DfaView::class), new LibraryRequirement('dompdf/dompdf', 'Missing\Dompdf\Dompdf'));

    expect(fn (): RenderedDfa => $renderer->render(resolve(BuildDfaDataAction::class)->handle(D::issued())))
        ->toThrow(function (DfaException $exception): void {
            expect($exception->errorCode)->toBe('dfa.renderer_unavailable')
                ->and($exception->context)->toBe(['package' => 'dompdf/dompdf']);
        });
});
