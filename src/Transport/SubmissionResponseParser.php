<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use Carbon\CarbonImmutable;
use DOMComment;
use DOMElement;
use DOMProcessingInstruction;
use DOMText;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

final readonly class SubmissionResponseParser
{
    private const array RESPONSE_FIELDS = ['EntryName', 'AuthorizationCode', 'SignatureTimeStamp', 'Messages'];

    private const array MESSAGE_FIELDS = ['Source', 'Type', 'DateTime', 'Code', 'Description', 'Location'];

    public function __construct(private SafeXmlParser $parser, private ClockInterface $clock) {}

    public function parse(PackagedArchive $archive, int $status, #[SensitiveParameter] string $body): SubmissionResult
    {
        if (\strlen($body) > Fiscal::MAX_RESPONSE_BYTES) {
            throw TransportException::because(TransportFailure::ResponseTooLarge, $archive, $status, \strlen($body));
        }

        $unexpected = TransportException::because(TransportFailure::UnexpectedResponse, $archive, $status, \strlen($body));
        $results    = [];
        foreach (self::elements($this->root($body) ?? throw $unexpected, 'Response') ?? throw $unexpected as $response) {
            $result = $this->entry($response) ?? throw $unexpected;
            if (! \in_array($result->entryName, $archive->entries, true) || isset($results[$result->entryName])) {
                throw $unexpected;
            }

            $results[$result->entryName] = $result;
        }

        if (\count($results) !== \count($archive->entries)) {
            throw $unexpected;
        }

        $entries = array_map(static fn (string $entry): EntryResult => $results[$entry], $archive->entries);

        return new SubmissionResult($archive->kind, $status, $entries, CarbonImmutable::instance($this->clock->now()));
    }

    private function root(string $body): ?DOMElement
    {
        if (! mb_check_encoding($body, 'UTF-8')) {
            return null;
        }

        try {
            $root = $this->parser->parse($body)->documentElement;
        } catch (SchemaValidationException) {
            return null;
        }

        return $root?->nodeName === 'Responses' && $root->namespaceURI === null ? $root : null;
    }

    private function entry(DOMElement $response): ?EntryResult
    {
        $fields    = self::fields($response, self::RESPONSE_FIELDS);
        $succeeded = match ($response->getAttribute('Succeeded')) {
            'true', '1'  => true,
            'false', '0' => false,
            default      => null,
        };
        $entryName = self::text($fields['EntryName'] ?? null);
        if ($fields === null || $succeeded === null || $entryName === null) {
            return null;
        }

        $authorizationCode = self::text($fields['AuthorizationCode'] ?? null);
        $messages          = isset($fields['Messages']) ? $this->messages($fields['Messages']) : [];
        if ($messages === null || $succeeded !== ($authorizationCode !== null) || (! $succeeded && $messages === [])) {
            return null;
        }

        $identifier = Str::beforeLast($entryName, '.xml');

        return new EntryResult($entryName, $identifier, $succeeded, $authorizationCode, self::text($fields['SignatureTimeStamp'] ?? null), $messages);
    }

    /**
     * @return list<RemoteMessage>|null
     */
    private function messages(DOMElement $messages): ?array
    {
        $elements = self::elements($messages, 'Message');
        if ($elements === null) {
            return null;
        }

        $parsed = [];
        foreach ($elements as $message) {
            $fields = self::fields($message, self::MESSAGE_FIELDS);
            $source = self::text($fields['Source'] ?? null);
            $type   = self::text($fields['Type'] ?? null);
            $code   = self::text($fields['Code'] ?? null);
            if ($source === null || $type === null || $code === null) {
                return null;
            }

            $parsed[] = new RemoteMessage(
                $source,
                $type,
                self::text($fields['DateTime'] ?? null),
                $code,
                self::text($fields['Description'] ?? null),
                self::text($fields['Location'] ?? null),
            );
        }

        return $parsed;
    }

    /**
     * @return list<DOMElement>|null
     */
    private static function elements(DOMElement $parent, string $name): ?array
    {
        $elements = self::significantChildren($parent);

        return $elements !== null && collect($elements)->every(static fn (DOMElement $element): bool => $element->nodeName === $name) ? $elements : null;
    }

    /**
     * @param  list<string>                   $allowed
     * @return array<string, DOMElement>|null
     */
    private static function fields(DOMElement $parent, array $allowed): ?array
    {
        $children = self::significantChildren($parent) ?? [];
        $fields   = collect($children)->keyBy(static fn (DOMElement $field): string => $field->nodeName);

        return $fields->count() === \count($children) && $fields->keys()->diff($allowed)->isEmpty() ? $fields->all() : null;
    }

    /**
     * @return list<DOMElement>|null
     */
    private static function significantChildren(DOMElement $parent): ?array
    {
        $children = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMComment) {
                continue;
            }

            if ($node instanceof DOMProcessingInstruction) {
                continue;
            }

            if ($node instanceof DOMText && Str::trim($node->wholeText, " \n\r\t") === '') {
                continue;
            }

            if (! $node instanceof DOMElement || $node->namespaceURI !== null) {
                return null;
            }

            $children[] = $node;
        }

        return $children;
    }

    private static function text(?DOMElement $element): ?string
    {
        if (! $element instanceof DOMElement || $element->childElementCount > 0) {
            return null;
        }

        $text = Str::trim($element->textContent, " \n\r\t");

        return $text === '' ? null : $text;
    }
}
