<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Support\Fiscal;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

use const SEEK_SET;

final readonly class BoundedSink implements StreamInterface
{
    private StreamInterface $buffer;

    public function __construct()
    {
        $this->buffer = Utils::streamFor(Utils::tryFopen('php://temp', 'w+'));
    }

    public function __toString(): string
    {
        return (string) $this->buffer;
    }

    public function write(string $string): int
    {
        $room = max(0, Fiscal::MAX_RESPONSE_BYTES + 1 - (int) $this->buffer->getSize());
        $this->buffer->write(substr($string, 0, $room));

        return \strlen($string);
    }

    public function close(): void
    {
        $this->buffer->close();
    }

    public function detach(): mixed
    {
        return $this->buffer->detach();
    }

    public function getSize(): ?int
    {
        return $this->buffer->getSize();
    }

    public function tell(): int
    {
        return $this->buffer->tell();
    }

    public function eof(): bool
    {
        return $this->buffer->eof();
    }

    public function isSeekable(): bool
    {
        return $this->buffer->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->buffer->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->buffer->rewind();
    }

    public function isWritable(): bool
    {
        return $this->buffer->isWritable();
    }

    public function isReadable(): bool
    {
        return $this->buffer->isReadable();
    }

    public function read(int $length): string
    {
        return $this->buffer->read($length);
    }

    public function getContents(): string
    {
        return $this->buffer->getContents();
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $this->buffer->getMetadata($key);
    }
}
