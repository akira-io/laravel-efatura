<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Exceptions\DfaException;
use Dompdf\Options;
use Throwable;

final readonly class DompdfOptions
{
    public const string PAPER = 'a4';

    private const int PERMISSIONS = 0o700;

    private const int SHARED_WRITE = 0o022;

    public function __construct(private EfaturaConfig $config, private string $defaultRoot) {}

    public function make(): Options
    {
        $root  = $this->config->dfa->cachePath ?? $this->defaultRoot;
        $owner = $this->effectiveOwner();
        $this->directory($root, $owner);

        return new Options([
            'defaultPaperSize'    => self::PAPER,
            'defaultFont'         => 'DejaVu Sans',
            'isRemoteEnabled'     => false,
            'isPhpEnabled'        => false,
            'isJavascriptEnabled' => false,
            'allowedProtocols'    => ['data://'],
            'fontCache'           => $this->directory($root . '/fonts', $owner),
            'tempDir'             => $this->directory($root . '/tmp', $owner),
            'chroot'              => [$this->directory($root . '/chroot', $owner)],
        ]);
    }

    private function effectiveOwner(): int
    {
        $probe  = tmpfile() ?: throw DfaException::cacheUnavailable();
        $status = fstat($probe);
        fclose($probe);

        return $status === false ? throw DfaException::cacheUnavailable() : $status['uid'];
    }

    private function directory(string $path, int $owner): string
    {
        try {
            $ready   = is_dir($path) || mkdir($path, self::PERMISSIONS, true) || is_dir($path);
            $private = $ready && fileowner($path) === $owner && (fileperms($path) & self::SHARED_WRITE) === 0;
        } catch (Throwable $throwable) {
            throw DfaException::cacheUnavailable($throwable);
        }

        return $private ? $path : throw DfaException::cacheUnavailable();
    }
}
