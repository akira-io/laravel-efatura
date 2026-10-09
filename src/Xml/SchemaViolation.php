<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Illuminate\Support\Str;
use LibXMLError;

use const PREG_OFFSET_CAPTURE;

final readonly class SchemaViolation
{
    private const string SUBJECT = '/\A(?:Element \'[^\']*\'(?:, attribute \'[^\']*\')?: )?(?:\[facet \'[A-Za-z]+\'\] )?/';

    public function __construct(public int $line, public int $column, public int $level, public string $message) {}

    public static function fromLibxml(LibXMLError $error): self
    {
        return new self($error->line, $error->column, $error->level, self::redact($error->message));
    }

    private static function redact(string $message): string
    {
        $message = Str::squish(mb_scrub($message, 'UTF-8'));
        $subject = preg_match(self::SUBJECT, $message, $match) === 1 ? $match[0] : '';
        $detail  = substr($message, \strlen($subject));

        if (preg_match('/(?<![A-Za-z])[\'"]/', $detail, $quote, PREG_OFFSET_CAPTURE) === 1) {
            $detail = substr($detail, 0, $quote[0][1]) . $quote[0][0] . '…' . $quote[0][0];
        }

        return $subject . $detail;
    }
}
