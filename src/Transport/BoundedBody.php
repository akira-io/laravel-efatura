<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Support\Fiscal;
use Illuminate\Http\Client\Response;
use SensitiveParameter;

final readonly class BoundedBody
{
    public function read(#[SensitiveParameter] Response $response, PackagedArchive|string|null $subject): string
    {
        $stream = $response->toPsrResponse()->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';
        while (! $stream->eof() && \strlen($body) <= Fiscal::MAX_RESPONSE_BYTES) {
            $chunk = $stream->read(Fiscal::MAX_RESPONSE_BYTES + 1 - \strlen($body));
            if ($chunk === '') {
                break;
            }

            $body .= $chunk;
        }

        if (\strlen($body) > Fiscal::MAX_RESPONSE_BYTES) {
            throw TransportException::because(TransportFailure::ResponseTooLarge, $subject, $response->status(), \strlen($body));
        }

        return $body;
    }

    public function answer(#[SensitiveParameter] Response $response, PackagedArchive|string|null $subject): string
    {
        try {
            return $this->read($response, $subject);
        } catch (TransportException $transportException) {
            throw_if($response->successful(), $transportException);

            throw TransportException::because(SubmissionStatus::failure($response->status()), $subject, $response->status());
        }
    }
}
