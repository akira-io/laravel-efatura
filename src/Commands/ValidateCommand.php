<?php

declare(strict_types=1);

namespace Akira\Efatura\Commands;

use Akira\Efatura\Actions\ValidateXmlDocumentAction;
use Akira\Efatura\Signing\SignatureVerification;
use Akira\Efatura\Xml\SchemaViolation;
use Akira\Efatura\Xml\XmlValidation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

#[Signature('efatura:validate {path : The signed XML file}
    {--profile= : enveloped or detached, detected from the root element when omitted}
    {--json : Print the result as a JSON document}')]
#[Description('Validate a signed e-Fatura XML file against the XSD and verify its signature')]
final class ValidateCommand extends Command
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    public function handle(ValidateXmlDocumentAction $validate): int
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

        return $this->report($validate->handle($xml, \is_string($option) ? XmlValidation::PROFILES[$option] : null));
    }

    private function refusal(string $path, ?string $profile): ?string
    {
        return match (true) {
            $profile !== null && ! \array_key_exists($profile, XmlValidation::PROFILES) => 'profile_invalid',
            ! file_exists($path)                                                        => 'file_missing',
            ! is_file($path)                                                            => 'file_not_regular',
            ! is_readable($path)                                                        => 'file_unreadable',
            default                                                                     => null,
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

    private function report(XmlValidation $validation): int
    {
        $valid = $validation->valid();

        if ((bool) $this->option('json')) {
            $this->line(json_encode($validation->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $valid ? self::SUCCESS : self::FAILURE;
        }

        $this->line(self::schemaLine($validation));
        foreach ($validation->violations as $violation) {
            $this->line('  ' . self::violationLine($violation));
        }

        if ($validation->signature instanceof SignatureVerification) {
            $this->presentSignature($validation->signature);
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

    private static function schemaLine(XmlValidation $validation): string
    {
        $profile = $validation->profileName();
        $code    = (string) $validation->schemaCode;

        if ($profile === null) {
            return __('efatura::efatura.console.validate.xml_refused', ['code' => $code]);
        }

        return $validation->schemaCode === null
            ? __('efatura::efatura.console.validate.schema_valid', ['profile' => $profile])
            : __('efatura::efatura.console.validate.schema_invalid', ['profile' => $profile, 'code' => $code]);
    }

    private static function violationLine(SchemaViolation $violation): string
    {
        return __('efatura::efatura.console.validate.violation', [
            'line'    => (string) $violation->line,
            'column'  => (string) $violation->column,
            'message' => $violation->message,
        ]);
    }
}
