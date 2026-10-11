<?php

declare(strict_types=1);

use Akira\Efatura\Sequence\NumberedDocument;
use Akira\Efatura\Sequence\SequenceScope;
use Akira\Efatura\Signing\SignedXml;
use Akira\Efatura\Signing\SigningCredentials;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Transformers\Transformer;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'ad', 'dieAndDump'])
    ->each->not->toBeUsed();

arch('runtime configuration never reads environment variables')
    ->expect('Akira\Efatura')
    ->not->toUse('env');

arch('data casts live in the Casts namespace and transform what they cast')
    ->expect('Akira\Efatura\Casts')
    ->toImplement(Cast::class)
    ->toImplement(Transformer::class);

arch('data properties attach a cast and its transformer together')
    ->expect('Akira\Efatura\Data')
    ->not->toUse([WithCast::class, WithTransformer::class]);

arch('data transformers live in the Transformers namespace')
    ->expect('Akira\Efatura\Transformers')
    ->toImplement(Transformer::class);

arch('money keeps only value objects and services')
    ->expect('Akira\Efatura\Money')
    ->not->toImplement(Cast::class)
    ->not->toImplement(Transformer::class);

arch('configuration values are immutable')
    ->expect('Akira\Efatura\Configuration')
    ->toBeReadonly();

arch('xml receives its collaborators instead of locating them')
    ->expect('Akira\Efatura\Xml')
    ->not->toUse(['Illuminate\Support\Facades', 'resolve', 'app']);

arch('actions receive their collaborators instead of locating them')
    ->expect('Akira\Efatura\Actions')
    ->not->toUse(['Illuminate\Support\Facades', 'resolve', 'app']);

arch('signing, sequences and packaging receive their collaborators and configuration')
    ->expect(['Illuminate\Support\Facades', 'resolve', 'app', 'config'])
    ->each->not->toBeUsedIn(['Akira\Efatura\Signing', 'Akira\Efatura\Sequence', 'Akira\Efatura\Packaging']);

arch('signing never runs processes or writes logs')
    ->expect('Akira\Efatura\Signing')
    ->not->toUse(['exec', 'shell_exec', 'proc_open', 'system', 'passthru', 'popen', 'Symfony\Component\Process', 'error_log', 'Psr\Log']);

arch('signing never dumps or serializes credentials')
    ->expect('Akira\Efatura\Signing')
    ->not->toUse(['serialize', 'var_export', 'var_dump', 'print_r']);

arch('signing, sequence and packaging values are immutable')
    ->expect([SigningCredentials::class, SignedXml::class, SequenceScope::class, NumberedDocument::class, 'Akira\Efatura\Packaging'])
    ->toBeReadonly();

arch('xml is written through the dom only')
    ->expect('Akira\Efatura\Xml')
    ->not->toUse(['simplexml_load_string', 'XMLWriter', 'sprintf', 'vsprintf']);

arch('actions expose a single handle entry point')
    ->expect('Akira\Efatura\Actions')
    ->toHaveMethod('handle')
    ->not->toHavePublicMethodsBesides(['__construct', 'handle']);

arch('transport, issuance and readiness receive their collaborators and configuration')
    ->expect(['Illuminate\Support\Facades', 'resolve', 'app', 'config'])
    ->each->not->toBeUsedIn([
        'Akira\Efatura\Transport',
        'Akira\Efatura\OAuth',
        'Akira\Efatura\Issuance',
        'Akira\Efatura\Readiness',
        'Akira\Efatura\Events',
        'Akira\Efatura\Testing',
    ]);

arch('the package reaches the network only through the injected laravel http client')
    ->expect([
        'curl_init',
        'curl_exec',
        'curl_multi_init',
        'curl_multi_exec',
        'fsockopen',
        'pfsockopen',
        'stream_socket_client',
        'fopen',
        Http::class,
    ])
    ->each->not->toBeUsedIn('Akira\Efatura');

arch('transport, oauth and readiness never read files')
    ->expect(['file_get_contents', 'file', 'readfile'])
    ->each->not->toBeUsedIn(['Akira\Efatura\Transport', 'Akira\Efatura\OAuth', 'Akira\Efatura\Readiness']);

arch('transport and oauth never log or dump what they exchange')
    ->expect(['Psr\Log', Log::class, 'logger', 'info', 'error_log', 'var_dump', 'var_export', 'print_r', 'serialize'])
    ->each->not->toBeUsedIn(['Akira\Efatura\Transport', 'Akira\Efatura\OAuth']);

arch('transport, oauth, issuance, readiness and event values are immutable')
    ->expect(['Akira\Efatura\Transport', 'Akira\Efatura\OAuth', 'Akira\Efatura\Issuance', 'Akira\Efatura\Readiness', 'Akira\Efatura\Events'])
    ->toBeReadonly();

it('never turns an http response into an exception that carries its body', function (): void {
    $throwing = collect(new Filesystem()->allFiles(__DIR__ . '/../src'))
        ->filter(fn (SplFileInfo $file): bool => preg_match('/->throw(If|Unless)?\(/', (string) file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values()
        ->all();

    expect($throwing)->toBe([]);
});

it('keeps source and tooling lines within 160 characters', function (): void {
    $longLines = collect(new Filesystem()->allFiles(__DIR__ . '/../src'))
        ->merge(new Filesystem()->allFiles(__DIR__ . '/../tools'))
        ->flatMap(fn (SplFileInfo $file): array => collect(file($file->getPathname()) ?: [])
            ->filter(fn (string $line): bool => mb_strlen(rtrim($line, "\n")) > 160)
            ->keys()
            ->map(fn (int $index): string => $file->getFilename() . ':' . ($index + 1))
            ->all())
        ->all();

    expect($longLines)->toBe([]);
});
