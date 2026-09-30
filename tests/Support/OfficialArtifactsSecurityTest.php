<?php

declare(strict_types=1);

use Akira\Efatura\Support\OfficialArtifacts;
use Akira\Efatura\Tests\Support\ArtifactFixture;

beforeEach(function (): void {
    $this->fixture = new ArtifactFixture;
});

afterEach(function (): void {
    $this->fixture->remove();
});

it('resolves only exact manifest known paths and profiles', function (): void {
    $artifacts = new OfficialArtifacts($this->fixture->root);

    expect($artifacts->path('nested/example.xsd'))->toBe($this->fixture->root . '/nested/example.xsd')
        ->and($artifacts->xsdEntry('EnvelopedSignature'))->toBe($this->fixture->root . '/nested/example.xsd');
});

it('rejects unsafe or unknown artifact paths', function (string $path): void {
    $this->fixture->manifest(['files' => [$path => ['sha256' => hash('sha256', "original\r\nbytes\r\n")]]]);

    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->path($path))
        ->toThrow(InvalidArgumentException::class);
})->with(['', '../example.xsd', '/nested/example.xsd', 'C:/nested/example.xsd', 'nested\example.xsd', "nested/example.xsd\0", './nested/example.xsd', 'nested/../example.xsd', 'nested//example.xsd']);

it('rejects files absent from the manifest', function (): void {
    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->path('official-artifacts.json'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects unknown signature profiles', function (): void {
    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->xsdEntry('enveloped'))
        ->toThrow(InvalidArgumentException::class);
});

it('detects modified bytes including line ending normalization on every lookup', function (): void {
    $artifacts = new OfficialArtifacts($this->fixture->root);
    $path      = $artifacts->path('nested/example.xsd');
    file_put_contents($path, "original\nbytes\n");

    expect(fn (): string => $artifacts->path('nested/example.xsd'))->toThrow(UnexpectedValueException::class);
});

it('rejects missing files and directories', function (bool $directory): void {
    unlink($this->fixture->root . '/nested/example.xsd');
    if ($directory) {
        mkdir($this->fixture->root . '/nested/example.xsd');
    }

    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->path('nested/example.xsd'))
        ->toThrow(UnexpectedValueException::class);
})->with([true, false]);

it('rejects symbolic links even when their target has the expected bytes', function (): void {
    rename($this->fixture->root . '/nested/example.xsd', $this->fixture->root . '/target.xsd');
    symlink($this->fixture->root . '/target.xsd', $this->fixture->root . '/nested/example.xsd');

    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->path('nested/example.xsd'))
        ->toThrow(UnexpectedValueException::class);
});

it('rejects symbolic link directories', function (): void {
    rename($this->fixture->root . '/nested', $this->fixture->root . '/target');
    symlink($this->fixture->root . '/target', $this->fixture->root . '/nested');

    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->path('nested/example.xsd'))
        ->toThrow(UnexpectedValueException::class);
});

it('fails safely when the resource root or manifest is missing', function (bool $root): void {
    unlink($this->fixture->root . '/official-artifacts.json');

    expect(fn (): OfficialArtifacts => new OfficialArtifacts($this->fixture->root . ($root ? '/absent' : '')))
        ->toThrow(UnexpectedValueException::class);
})->with([true, false]);

it('rejects an invalid manifest', function (string $contents): void {
    file_put_contents($this->fixture->root . '/official-artifacts.json', $contents);

    expect(fn (): OfficialArtifacts => new OfficialArtifacts($this->fixture->root))->toThrow(UnexpectedValueException::class);
})->with(['{', 'null', '{}', '{"files":[],"signature_profiles":null}']);

it('rejects invalid checksum records', function (mixed $record): void {
    $this->fixture->manifest(['files' => ['nested/example.xsd' => $record]]);

    expect(fn (): string => new OfficialArtifacts($this->fixture->root)->path('nested/example.xsd'))
        ->toThrow(UnexpectedValueException::class);
})->with([[false], [['sha256' => 123]], [['sha256' => 'invalid']]]);
