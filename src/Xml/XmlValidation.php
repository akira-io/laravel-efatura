<?php

declare(strict_types=1);

namespace Akira\Efatura\Xml;

use Akira\Efatura\Enums\SignatureFailure;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Exceptions\EfaturaException;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Signing\SignatureVerification;

/**
 * @phpstan-type PresentedSignature array{valid: bool, failures: list<string>, certificate: array{subject: string, serial_number: string, digest: string}|null}
 * @phpstan-type PresentedSchema array{valid: bool, code: string|null, violations: list<array{line: int, column: int, message: string}>, violations_total: int}
 */
final readonly class XmlValidation
{
    public const array PROFILES = ['enveloped' => SignatureProfile::Enveloped, 'detached' => SignatureProfile::InternallyDetached];

    public const int MAX_BYTES = 5 * 1024 * 1024;

    public const int MAX_PRESENTED_VIOLATIONS = 100;

    /**
     * @param list<SchemaViolation> $violations
     */
    private function __construct(
        public ?SignatureProfile $profile,
        public ?string $schemaCode,
        public array $violations,
        public ?SignatureVerification $signature,
    ) {}

    public static function refused(SchemaValidationException $exception): self
    {
        return new self(null, $exception->errorCode, $exception->violations, null);
    }

    public static function checked(SignatureProfile $profile, ?EfaturaException $failure, ?SignatureVerification $signature): self
    {
        $violations = $failure instanceof SchemaValidationException ? $failure->violations : [];

        return new self($profile, $failure?->errorCode, $violations, $signature);
    }

    public function valid(): bool
    {
        return $this->schemaCode === null && $this->signature?->valid === true;
    }

    /**
     * @return list<SchemaViolation>
     */
    public function presentedViolations(): array
    {
        return \array_slice($this->violations, 0, self::MAX_PRESENTED_VIOLATIONS);
    }

    public function profileName(): ?string
    {
        $name = array_search($this->profile, self::PROFILES, true);

        return \is_string($name) ? $name : null;
    }

    /**
     * @return array{valid: bool, profile: string|null, schema: PresentedSchema, signature: PresentedSignature|null, trust_chain_verified: false}
     */
    public function toArray(): array
    {
        return [
            'valid'   => $this->valid(),
            'profile' => $this->profileName(),
            'schema'  => [
                'valid'      => $this->schemaCode === null,
                'code'       => $this->schemaCode,
                'violations' => array_map(static fn (SchemaViolation $violation): array => [
                    'line'    => $violation->line,
                    'column'  => $violation->column,
                    'message' => $violation->message,
                ], $this->presentedViolations()),
                'violations_total' => \count($this->violations),
            ],
            'signature'            => $this->signature instanceof SignatureVerification ? self::signature($this->signature) : null,
            'trust_chain_verified' => false,
        ];
    }

    /**
     * @return PresentedSignature
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
