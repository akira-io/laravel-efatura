<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use DOMElement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use SensitiveParameter;

final readonly class TransportExchange
{
    public function __construct(
        private TransportRequests $requests,
        private ReadRetries $retries,
        private BoundedBody $body,
        private SubmissionResponseParser $parser,
        private FailureClassifier $failures,
        private SafeXmlParser $xml,
        private ParseIudAction $parseIud,
    ) {}

    public function document(string $iud): IudData
    {
        return $this->parseIud->handle($iud);
    }

    /**
     * @param array<string, string> $headers
     */
    public function post(HttpClientConfig $config, PackagedArchive $archive, #[SensitiveParameter] array $headers): Response
    {
        $resource = $archive->kind->value;

        try {
            return $this->requests->for($config, $archive)
                ->withHeaders($headers)
                ->attach('file', $archive->bytes, $resource . '.zip', ['Content-Type' => 'application/octet-stream'])
                ->post($resource);
        } catch (ConnectionException $connectionException) {
            throw $this->failures->classify($connectionException, $archive);
        }
    }

    public function submission(PackagedArchive $archive, #[SensitiveParameter] Response $response): SubmissionResult
    {
        $body = $this->body->answer($response, $archive);

        try {
            return $this->parser->parse($archive, $response->status(), $body);
        } catch (TransportException $transportException) {
            throw_if($response->successful(), $transportException);

            throw TransportException::because(SubmissionStatus::failure($response->status()), $archive, $response->status(), \strlen($body));
        }
    }

    /**
     * @param array<string, string> $headers
     */
    public function fetch(HttpClientConfig $config, string $iud, #[SensitiveParameter] array $headers): Response
    {
        $request = $this->requests->for($config, $this->document($iud)->repository)->withHeaders($headers);

        try {
            return $this->retries->apply($request, $config)->get('dfe/xml/' . $iud);
        } catch (ConnectionException $connectionException) {
            throw $this->failures->classify($connectionException, $iud);
        }
    }

    public function lookup(string $iud, #[SensitiveParameter] Response $response): DocumentLookup
    {
        $status = $response->status();
        if ($status === 404) {
            return new DocumentLookup($iud, false, null);
        }

        throw_unless($response->successful(), TransportException::because(SubmissionStatus::readFailure($status), $iud, $status));

        $xml        = $this->body->read($response, $iud);
        $unexpected = TransportException::because(TransportFailure::UnexpectedResponse, $iud, $status, \strlen($xml));
        throw_unless($status === 200 && $this->identifies($xml, $iud), $unexpected);

        return new DocumentLookup($iud, true, $xml);
    }

    private function identifies(string $xml, string $iud): bool
    {
        try {
            $outer = $this->xml->parse($xml)->documentElement;
        } catch (SchemaValidationException) {
            return false;
        }

        $root = $outer?->localName === Fiscal::DETACHED_SIGNATURE_ROOT && $outer->namespaceURI === null ? $outer->lastElementChild : $outer;

        return $root instanceof DOMElement
            && $root->localName === 'Dfe'
            && $root->namespaceURI === Fiscal::XML_NAMESPACE
            && $root->getAttribute('Id') === $iud;
    }
}
