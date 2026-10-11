<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Configuration\HttpClientConfig;
use RuntimeException;
use Symfony\Component\Process\Process;

use const PHP_BINARY;
use const PHP_EOL;

final readonly class LoopbackServer
{
    private function __construct(private Process $process, private int $port) {}

    public static function start(string $mode): self
    {
        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/../Fixtures/loopback-server.php', $mode]);
        $process->start();
        $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, PHP_EOL));

        return new self($process, (int) trim($process->getOutput()));
    }

    public static function refusing(): HttpClientConfig
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('No loopback port is free.');
        $name   = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return self::profile((int) substr($name, (int) strrpos($name, ':') + 1));
    }

    public function config(int $timeoutSeconds = 1): HttpClientConfig
    {
        return self::profile($this->port, $timeoutSeconds);
    }

    public function receivedBytes(): ?int
    {
        return preg_match('/^received (\d+)$/m', $this->process->getOutput(), $match) === 1 ? (int) $match[1] : null;
    }

    public function stop(): void
    {
        $this->process->stop(0);
    }

    private static function profile(int $port, int $timeoutSeconds = 1): HttpClientConfig
    {
        return new HttpClientConfig('http://127.0.0.1:' . $port . '/v1/', $timeoutSeconds, 1, 0, 0, 1, true);
    }
}
