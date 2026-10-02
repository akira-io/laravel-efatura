<?php

declare(strict_types=1);

use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Support\OfficialArtifacts;
use Akira\Efatura\Tests\Support\ArtifactFixture;
use Akira\Efatura\Tests\Support\ExceptionTrace;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->fixture = new ArtifactFixture;
    $this->files   = new Filesystem;
});

afterEach(function (): void {
    $this->fixture->remove();
});

it('resolves only exact manifest known paths and profiles', function (): void {
    $artifacts = $this->fixture->artifacts();

    expect($artifacts->path('nested/example.xsd'))->toBe($this->fixture->root . '/nested/example.xsd')
        ->and($artifacts->xsdEntry('EnvelopedSignature'))->toBe($this->fixture->root . '/nested/example.xsd');
});

it('rejects unsafe or unknown artifact paths', function (string $path): void {
    $this->fixture->manifest(['files' => [$path => ['sha256' => hash('sha256', "original\r\nbytes\r\n")]]]);

    expect(fn (): string => $this->fixture->artifacts()->path($path))
        ->toThrow(OfficialArtifactException::class, 'artifacts.unknown_or_unsafe_path');
})->with(['', '../example.xsd', '/nested/example.xsd', 'C:/nested/example.xsd', 'nested\example.xsd', "nested/example.xsd\0", './nested/example.xsd', 'nested/../example.xsd', 'nested//example.xsd']);

it('rejects files absent from the manifest', function (): void {
    expect(fn (): string => $this->fixture->artifacts()->path('official-artifacts.json'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.unknown_or_unsafe_path');
});

it('rejects unknown signature profiles', function (): void {
    expect(fn (): string => $this->fixture->artifacts()->xsdEntry('enveloped'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.unknown_profile');
});

it('detects modified bytes including line ending normalization on every lookup', function (): void {
    $artifacts = $this->fixture->artifacts();
    $path      = $artifacts->path('nested/example.xsd');
    $this->files->put($path, "original\nbytes\n");

    expect(fn (): string => $artifacts->path('nested/example.xsd'))->toThrow(OfficialArtifactException::class, 'artifacts.size_mismatch');

    $this->files->put($path, "changed!\r\nbytes\r\n");

    expect(fn (): string => $artifacts->path('nested/example.xsd'))->toThrow(OfficialArtifactException::class, 'artifacts.checksum_mismatch');
});

it('rejects missing files and directories', function (bool $directory): void {
    $this->files->delete($this->fixture->root . '/nested/example.xsd');
    if ($directory) {
        $this->files->ensureDirectoryExists($this->fixture->root . '/nested/example.xsd');
    }

    expect(fn (): string => $this->fixture->artifacts()->path('nested/example.xsd'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.missing_or_unreadable');
})->with([true, false]);

it('rejects symbolic links even when their target has the expected bytes', function (): void {
    $this->files->move($this->fixture->root . '/nested/example.xsd', $this->fixture->root . '/target.xsd');
    symlink($this->fixture->root . '/target.xsd', $this->fixture->root . '/nested/example.xsd');

    expect(fn (): string => $this->fixture->artifacts()->path('nested/example.xsd'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.symbolic_link');
});

it('rejects symbolic link directories', function (): void {
    $this->files->moveDirectory($this->fixture->root . '/nested', $this->fixture->root . '/target');
    symlink($this->fixture->root . '/target', $this->fixture->root . '/nested');

    expect(fn (): string => $this->fixture->artifacts()->path('nested/example.xsd'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.symbolic_link');
});

it('fails safely when the resource root or manifest is missing', function (bool $root): void {
    $this->files->delete($this->fixture->root . '/official-artifacts.json');

    expect(fn (): OfficialArtifacts => new OfficialArtifacts(new Filesystem, $this->fixture->root . ($root ? '/absent' : '')))
        ->toThrow(OfficialArtifactException::class, $root ? 'artifacts.missing_root' : 'artifacts.missing_or_unreadable');
})->with([true, false]);

it('normalizes a native root canonicalization failure without exposing the supplied path', function (): void {
    $original  = ini_set('zend.exception_ignore_args', '0');
    $resources = $this->fixture->root . "/synthetic-private-root\0invalid";

    try {
        expect(fn (): OfficialArtifacts => new OfficialArtifacts(new Filesystem, $resources))
            ->toThrow(function (OfficialArtifactException $exception) use ($resources): void {
                $dump = print_r(ExceptionTrace::packageArguments($exception), true);

                expect($exception->errorCode)->toBe('artifacts.missing_root')
                    ->and($exception->getMessage())->toBe('artifacts.missing_root')
                    ->and($exception->context)->toBe(['operation' => 'load_manifest'])
                    ->and($exception->getPrevious())->toBeNull()
                    ->and($exception->getTrace()[0]['args'][1] ?? null)->toBeInstanceOf(SensitiveParameterValue::class)
                    ->and($dump)->not->toContain($resources)
                    ->and($dump)->not->toContain('synthetic-private-root');
            });
    } finally {
        ini_set('zend.exception_ignore_args', $original);
    }
});

it('rejects an invalid manifest', function (string $contents): void {
    $this->files->put($this->fixture->root . '/official-artifacts.json', $contents);

    expect(fn (): OfficialArtifacts => $this->fixture->artifacts())->toThrow(OfficialArtifactException::class, 'artifacts.invalid_manifest');
})->with(['{', 'null', '"string"', '123', '{}', '{"files":[],"signature_profiles":null}']);

it('rejects invalid checksum records', function (mixed $record): void {
    $this->fixture->manifest(['files' => ['nested/example.xsd' => $record]]);

    expect(fn (): string => $this->fixture->artifacts()->path('nested/example.xsd'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.invalid_checksum');
})->with([[false], [['sha256' => 123]], [['sha256' => 'invalid']]]);

it('rejects non-integer and negative manifest sizes', function (mixed $size): void {
    $this->fixture->manifest(['files' => ['nested/example.xsd' => [
        'sha256' => hash('sha256', "original\r\nbytes\r\n"),
        'size'   => $size,
    ]]]);

    expect(fn (): string => $this->fixture->artifacts()->path('nested/example.xsd'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.invalid_size');
})->with(['17', 17.5, null, -1]);

it('rejects a manifest size that differs from the file bytes', function (): void {
    $this->fixture->manifest(['files' => ['nested/example.xsd' => [
        'sha256' => hash('sha256', "original\r\nbytes\r\n"),
        'size'   => 18,
    ]]]);

    expect(fn (): string => $this->fixture->artifacts()->path('nested/example.xsd'))
        ->toThrow(OfficialArtifactException::class, 'artifacts.size_mismatch');
});

it('rejects a same-byte symlink swapped in during hashing without leaking the root', function (): void {
    $root       = $this->fixture->root;
    $filesystem = new class ($root) extends Filesystem
    {
        public function __construct(private readonly string $root) {}

        public function hash($path, $algorithm = 'md5')
        {
            if ($path === $this->root . '/nested/example.xsd') {
                $this->move($path, $this->root . '/target.xsd');
                symlink($this->root . '/target.xsd', $path);
            }

            return parent::hash($path, $algorithm);
        }
    };

    $artifacts = new OfficialArtifacts($filesystem, $root);

    expect(fn (): string => $artifacts->path('nested/example.xsd'))
        ->toThrow(function (OfficialArtifactException $officialArtifactException) use ($root): void {
            expect($officialArtifactException->errorCode)->toBeIn(['artifacts.symbolic_link', 'artifacts.missing_or_unreadable'])
                ->and($officialArtifactException->getMessage())->not->toContain($root)
                ->and(json_encode($officialArtifactException->context, JSON_THROW_ON_ERROR))->not->toContain($root)
                ->and($officialArtifactException->getPrevious())->toBeNull()
                ->and(json_encode($officialArtifactException->getTrace(), JSON_THROW_ON_ERROR))->not->toContain($root);
        });
});

it('returns safe errors for failed filesystem checks', function (string $method, string $errorCode): void {
    $root       = $this->fixture->root;
    $filesystem = new class ($root, $method) extends Filesystem
    {
        public function __construct(private readonly string $root, private readonly string $failure) {}

        public function isDirectory($directory)
        {
            if ($this->failure === 'isDirectoryFalse') {
                return false;
            }

            if ($this->failure === 'isDirectory') {
                throw new RuntimeException($this->root);
            }

            return parent::isDirectory($directory);
        }

        public function isFile($file)
        {
            if ($this->failure === 'isFile') {
                throw new RuntimeException($this->root);
            }

            return parent::isFile($file);
        }

        public function size($path)
        {
            if ($this->failure === 'size') {
                throw new RuntimeException($this->root);
            }

            return parent::size($path);
        }

        public function hash($path, $algorithm = 'md5')
        {
            if ($this->failure === 'hash') {
                throw new RuntimeException($this->root);
            }

            return parent::hash($path, $algorithm);
        }
    };

    expect(fn (): string => $this->fixture->artifacts($filesystem)->path('nested/example.xsd'))
        ->toThrow(function (OfficialArtifactException $officialArtifactException) use ($root, $errorCode): void {
            expect($officialArtifactException->errorCode)->toBe($errorCode)
                ->and($officialArtifactException->getMessage())->not->toContain($root)
                ->and(json_encode($officialArtifactException->context, JSON_THROW_ON_ERROR))->not->toContain($root)
                ->and($officialArtifactException->getPrevious())->toBeNull()
                ->and(json_encode($officialArtifactException->getTrace(), JSON_THROW_ON_ERROR))->not->toContain($root);
        });
})->with([
    ['isDirectory', 'artifacts.missing_root'],
    ['isDirectoryFalse', 'artifacts.missing_root'],
    ['isFile', 'artifacts.missing_or_unreadable'],
    ['size', 'artifacts.missing_or_unreadable'],
    ['hash', 'artifacts.checksum_mismatch'],
]);
