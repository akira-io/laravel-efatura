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

    private const string DEFAULT_DIRECTORY = 'efatura-dompdf';

    private const int PERMISSIONS = 0o700;

    public function __construct(private EfaturaConfig $config) {}

    public function make(): Options
    {
        $root = $this->config->dfa->cachePath ?? sys_get_temp_dir() . '/' . self::DEFAULT_DIRECTORY;

        return new Options([
            'defaultPaperSize'    => self::PAPER,
            'defaultFont'         => 'DejaVu Sans',
            'isRemoteEnabled'     => false,
            'isPhpEnabled'        => false,
            'isJavascriptEnabled' => false,
            'allowedProtocols'    => ['data://'],
            'fontCache'           => $this->directory($root . '/fonts'),
            'tempDir'             => $this->directory($root . '/tmp'),
            'chroot'              => [$this->directory($root . '/chroot')],
        ]);
    }

    private function directory(string $path): string
    {
        try {
            $ready = is_dir($path) || mkdir($path, self::PERMISSIONS, true) || is_dir($path);
        } catch (Throwable $throwable) {
            throw DfaException::cacheUnavailable($throwable);
        }

        return $ready ? $path : throw DfaException::cacheUnavailable();
    }
}
