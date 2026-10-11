<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Akira\Efatura\Actions\VerifyXmlSignatureAction;
use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use Akira\Efatura\Xml\SchemaViolation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @phpstan-type SchemaOutcome array{valid: bool, code: string|null, violations: list<array{line: int, column: int, message: string}>}
 */
#[Signature('efatura:validate {path : The signed XML file}
    {--profile= : enveloped or detached, detected from the root element when omitted}
    {--json : Print the result as a JSON document}')]
#[Description('Validate a signed e-Fatura XML file against the XSD and verify its signature')]
final class ValidateCommand extends Command
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    private const array PROFILES = ['enveloped' => SignatureProfile::Enveloped, 'detached' => SignatureProfile::InternallyDetached];

    public function handle(SafeXmlParser $parser, SchemaValidator $schemas, VerifyXmlSignatureAction $signatures): int
    {
        $path    = $this->argument('path');
        $option  = $this->option('profile');
        $refusal = $this->refusal($path, \is_string($option) ? $option : null);
        if ($refusal !== null) {
            return $this->refuse($refusal, $path);
        }

        $xml = (string) file_get_contents($path, length: self::MAX_BYTES + 1);
        if (\strlen($xml) > self::MAX_BYTES) {
            return $this->refuse('file_too_large', $path);
        }

        try {
            $root = $parser->parse($xml)->documentElement?->localName;
        } catch (SchemaValidationException $schemaValidationException) {
            return $this->report(null, self::schemaFailure($schemaValidationException), null);
        }

        $name = \is_string($option) ? $option : ($root === Fiscal::DETACHED_SIGNATURE_ROOT ? 'detached' : 'enveloped');

        return $this->report($name, self::schema($schemas, $xml, self::PROFILES[$name]), $signatures->handle($xml));
    }

    private function refusal(string $path, ?string $profile): ?string
    {
        return match (true) {
            $profile !== null && ! \array_key_exists($profile, self::PROFILES) => 'profile_invalid',
            ! file_exists($path)                                               => 'file_missing',
            ! is_file($path)                                                   => 'file_not_regular',
            ! is_readable($path)                                               => 'file_unreadable',
            default                                                            => null,
        };
    }

    private function refuse(string $reason, string $path): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode(['valid' => false, 'error' => $reason], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }

        error(__('efatura::efatura.console.validate.' . $reason, ['path' => $path, 'limit' => self::MAX_BYTES]));

        return self::FAILURE;
    }

    /**
     * @param SchemaOutcome $schema
     */
    private function report(?string $profile, array $schema, ?SignatureVerification $signature): int
    {
        $valid = $schema['valid'] && $signature?->valid === true;

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'valid'                => $valid,
                'profile'              => $profile,
                'schema'               => $schema,
                'signature'            => $signature instanceof SignatureVerification ? self::signature($signature) : null,
                'trust_chain_verified' => false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $valid ? self::SUCCESS : self::FAILURE;
        }

        $this->line(self::schemaLine($schema, $profile));
        foreach ($schema['violations'] as $violation) {
            $this->line('  ' . __('efatura::efatura.console.validate.violation', $violation));
        }

        if ($signature instanceof SignatureVerification) {
            $this->presentSignature($signature);
        }

        $valid ? info(__('efatura::efatura.console.validate.valid')) : error(__('efatura::efatura.console.validate.invalid'));

        return $valid ? self::SUCCESS : self::FAILURE;
    }

    private function presentSignature(SignatureVerification $signature): void
    {
        $this->line(__('efatura::efatura.console.validate.' . ($signature->valid ? 'signature_valid' : 'signature_invalid')));

        foreach ($signature->failures as $failure) {
            $this->line('  ' . __('efatura::efatura.console.validate.failures.' . $failure->value) . ' (' . $failure->code() . ')');
        }

        if ($signature->certificateSubject !== null) {
            $this->line(__('efatura::efatura.console.validate.certificate', [
                'subject' => $signature->certificateSubject,
                'serial'  => (string) $signature->certificateSerialNumber,
                'digest'  => (string) $signature->certificateDigest,
            ]));
        }

        note(__('efatura::efatura.console.validate.trust_chain'));
    }

    /**
     * @param SchemaOutcome $schema
     */
    private static function schemaLine(array $schema, ?string $profile): string
    {
        if ($profile === null) {
            return __('efatura::efatura.console.validate.xml_refused', ['code' => (string) $schema['code']]);
        }

        return $schema['valid']
            ? __('efatura::efatura.console.validate.schema_valid', ['profile' => $profile])
            : __('efatura::efatura.console.validate.schema_invalid', ['profile' => $profile, 'code' => (string) $schema['code']]);
    }

    /**
     * @return SchemaOutcome
     */
    private static function schema(SchemaValidator $schemas, string $xml, SignatureProfile $profile): array
    {
        try {
            $schemas->validate($xml, $profile);
        } catch (EfaturaException $efaturaException) {
            return self::schemaFailure($efaturaException);
        }

        return ['valid' => true, 'code' => null, 'violations' => []];
    }

    /**
     * @return SchemaOutcome
     */
    private static function schemaFailure(EfaturaException $exception): array
    {
        $violations = $exception instanceof SchemaValidationException ? $exception->violations : [];

        return [
            'valid'      => false,
            'code'       => $exception->errorCode,
            'violations' => array_map(static fn (SchemaViolation $violation): array => [
                'line'    => $violation->line,
                'column'  => $violation->column,
                'message' => $violation->message,
            ], $violations),
        ];
    }

    /**
     * @return array{valid: bool, failures: list<string>, certificate: array{subject: string, serial_number: string, digest: string}|null}
     */
    private static function signature(SignatureVerification $verification): array
    {
        return [
            'valid'       => $verification->valid,
            'failures'    => array_map(static fn (SignatureFailure $failure): string => $failure->code(), $verification->failures),
            'certificate' => $verification->certificateSubject === null ? null : [
                'subject'       => $verification->certificateSubject,
                'serial_number' => (string) $verification->certificateSerialNumber,
                'digest'        => (string) $verification->certificateDigest,
            ],
        ];
    }
}
