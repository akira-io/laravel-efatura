<?php

declare(strict_types=1);

use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Transport\BoundedSink;

it('keeps one byte past the response limit and reports every byte as written', function (): void {
    $sink  = new BoundedSink;
    $chunk = str_repeat('a', 1024 * 1024);

    $written = array_sum(array_map(fn (): int => $sink->write($chunk), range(1, 6)));

    expect($written)->toBe(6 * 1024 * 1024)
        ->and($sink->getSize())->toBe(Fiscal::MAX_RESPONSE_BYTES + 1)
        ->and($sink->write('tail'))->toBe(4)
        ->and($sink->getSize())->toBe(Fiscal::MAX_RESPONSE_BYTES + 1);
});

it('behaves as a readable and seekable stream over what it kept', function (): void {
    $sink = new BoundedSink;
    $sink->write('<Responses/>');

    expect($sink->tell())->toBe(12)
        ->and($sink->eof())->toBeFalse()
        ->and($sink->isSeekable())->toBeTrue()
        ->and($sink->isReadable())->toBeTrue()
        ->and($sink->isWritable())->toBeTrue()
        ->and($sink->getMetadata('uri'))->toBe('php://temp')
        ->and((string) $sink)->toBe('<Responses/>');

    $sink->seek(1);
    expect($sink->read(9))->toBe('Responses');

    $sink->rewind();
    expect($sink->getContents())->toBe('<Responses/>')
        ->and($sink->eof())->toBeTrue()
        ->and($sink->detach())->toBeResource();

    $sink->close();
});
