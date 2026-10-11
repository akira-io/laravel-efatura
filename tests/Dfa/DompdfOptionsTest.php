<?php

declare(strict_types=1);

use Akira\Efatura\Actions\BuildDfaDataAction;
use Akira\Efatura\Contracts\DfaRenderer;
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Dfa\DompdfOptions;
use Akira\Efatura\Exceptions\DfaException;
use Akira\Efatura\Tests\Support\DfaFixtures as D;
use Akira\Efatura\Tests\Support\DocumentFixtures as F;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->cache = sys_get_temp_dir() . '/efatura-dompdf-test-' . bin2hex(random_bytes(6));
    config()->set('efatura.dfa.cache_path', $this->cache);
});

afterEach(function (): void {
    new Filesystem()->deleteDirectory($this->cache);
});

function vendorFonts(): array
{
    $directory = dirname((new ReflectionClass(Options::class))->getFileName(), 2) . '/lib/fonts';

    return collect(new Filesystem()->files($directory))
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getFilename() => [$file->getSize(), $file->getMTime()]])
        ->all();
}

it('keeps the font cache, the temporary files and the chroot in the configured directory', function (): void {
    $options = resolve(DompdfOptions::class)->make();

    expect($options->getFontCache())->toBe($this->cache . '/fonts')
        ->and($options->getTempDir())->toBe($this->cache . '/tmp')
        ->and($options->getChroot())->toBe([$this->cache . '/chroot'])
        ->and(collect([$this->cache . '/fonts', $this->cache . '/tmp', $this->cache . '/chroot'])->every(fn (string $directory): bool => is_dir($directory)))->toBeTrue()
        ->and(new Filesystem()->allFiles($this->cache . '/chroot'))->toBe([])
        ->and(decoct(fileperms($this->cache) & 0o777))->toBe('700');
});

it('falls back to a directory of its own under the system temp directory', function (): void {
    config()->set('efatura.dfa.cache_path', null);

    expect(resolve(DompdfOptions::class)->make()->getFontCache())->toBe(sys_get_temp_dir() . '/efatura-dompdf/fonts')
        ->and(is_dir(sys_get_temp_dir() . '/efatura-dompdf/chroot'))->toBeTrue();
});

it('turns off remote resources, embedded PHP and JavaScript and accepts data URIs only', function (): void {
    $options = resolve(DompdfOptions::class)->make();

    expect($options->getIsRemoteEnabled())->toBeFalse()
        ->and($options->getIsPhpEnabled())->toBeFalse()
        ->and($options->getIsJavascriptEnabled())->toBeFalse()
        ->and(array_keys($options->getAllowedProtocols()))->toBe(['data://'])
        ->and($options->getDefaultPaperSize())->toBe('a4')
        ->and($options->getDefaultFont())->toBe('DejaVu Sans');
});

it('renders without writing into the dompdf package', function (): void {
    $before = vendorFonts();

    $rendered = resolve(DfaRenderer::class)->render(resolve(BuildDfaDataAction::class)->handle(D::issued()));

    expect($rendered->bytes)->toStartWith('%PDF-')
        ->and(vendorFonts())->toBe($before)
        ->and(new Filesystem()->glob($this->cache . '/fonts/*.ufm.json'))->not->toBe([]);
});

it('reports a cache directory that cannot be created', function (): void {
    $file = $this->cache . '-file';
    new Filesystem()->put($file, '');
    config()->set('efatura.dfa.cache_path', $file . '/dompdf');

    try {
        expect(fn (): Options => resolve(DompdfOptions::class)->make())
            ->toThrow(function (DfaException $exception): void {
                expect($exception->errorCode)->toBe('dfa.cache_unavailable')
                    ->and($exception->field)->toBe('efatura.dfa.cache_path')
                    ->and($exception->context)->toBe([]);
            });
    } finally {
        new Filesystem()->delete($file);
    }
});

it('embeds no remote or local file a document points at, and only data URIs', function (): void {
    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    new Filesystem()->put($this->cache . '-pixel.png', $pixel);
    $render = function (string $source): string {
        $dompdf = new Dompdf(resolve(DompdfOptions::class)->make());
        $dompdf->loadHtml('<p>x</p><img src="' . $source . '">');
        $dompdf->render();

        return (string) $dompdf->output();
    };

    try {
        expect($render('https://example.invalid/x.png'))->not->toContain('/Subtype /Image')
            ->and($render('file://' . $this->cache . '-pixel.png'))->not->toContain('/Subtype /Image')
            ->and($render('file:///etc/passwd'))->not->toContain('/Subtype /Image')
            ->and($render('data:image/png;base64,' . base64_encode($pixel)))->toContain('/Subtype /Image');
    } finally {
        new Filesystem()->delete($this->cache . '-pixel.png');
    }
});

it('prints markup inside a document value as text', function (): void {
    $line     = LineItemData::from(F::linePayload(['item' => ['description' => '<img src="https://example.invalid/x.png"><img src="file:///etc/passwd">', 'emitterIdentification' => 'SKU']]));
    $document = new ElectronicInvoiceData(...[...get_object_vars(D::off()), 'lines' => [$line]]);

    $pdf = resolve(DfaRenderer::class)->render(resolve(BuildDfaDataAction::class)->handle(D::prepared($document)))->bytes;

    expect($pdf)->toStartWith('%PDF-')
        ->not->toContain('/Subtype /Image');
});
