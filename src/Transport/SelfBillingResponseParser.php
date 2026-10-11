<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Data\IudData;
use Akira\Efatura\Data\SelfBillingAuthorizationRequestData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Support\FiscalRules;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use SensitiveParameter;

use const JSON_THROW_ON_ERROR;

final readonly class SelfBillingResponseParser
{
    private const array MESSAGE_FIELDS = ['source', 'type', 'dateTime', 'code', 'description', 'location'];

    public function __construct(private ParseIudAction $parseIud) {}

    public function parse(
        SelfBillingAuthorizationRequestData $request,
        Environment $repository,
        int $status,
        #[SensitiveParameter]
        string $body,
    ): SelfBillingAuthorization {
        $unexpected = TransportException::because(TransportFailure::UnexpectedResponse, status: $status, bytes: \strlen($body));
        $decoded    = self::decode($body) ?? throw $unexpected;

        return match ($decoded['succeeded'] ?? null) {
            true, 'true'   => $this->authorization($request, $repository, $decoded['payload'] ?? null) ?? throw $unexpected,
            false, 'false' => throw TransportException::rejected(self::messages($decoded['messages'] ?? null) ?? throw $unexpected, $status),
            default        => throw $unexpected,
        };
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decode(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, Fiscal::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    private function authorization(SelfBillingAuthorizationRequestData $request, Environment $repository, mixed $payload): ?SelfBillingAuthorization
    {
        if (! \is_array($payload)) {
            return null;
        }

        $id      = $payload['authorizationId'] ?? null;
        $expires = self::integer($payload['authorizationCodeExpirationSeconds'] ?? null);
        $iud     = $payload['iud'] ?? null;
        $series  = $payload['serie'] ?? null;
        $led     = self::integer($payload['ledCode'] ?? null);
        $number  = self::integer($payload['documentNumber'] ?? null);
        if (! \is_string($id) || ! \is_int($expires) || ! \is_string($iud) || ! \is_string($series) || ! \is_int($led) || ! \is_int($number)) {
            return null;
        }

        $identified = $this->identifies($iud, $request, $repository, $led, $number);

        return $identified && Str::isUuid($id) && $expires > 0 && FiscalRules::isSeries($series)
            ? new SelfBillingAuthorization($id, $expires, $iud, $series, $led, $number)
            : null;
    }

    private static function integer(mixed $value): ?int
    {
        if (\is_string($value) && preg_match('/\A\d{1,18}\z/', $value) === 1) {
            return (int) $value;
        }

        return \is_int($value) ? $value : null;
    }

    private function identifies(string $iud, SelfBillingAuthorizationRequestData $request, Environment $repository, int $led, int $number): bool
    {
        try {
            $parsed = $this->parseIud->handle($iud);
        } catch (ValidationException) {
            return false;
        }

        return self::describes($parsed, $request, $repository) && $parsed->ledCode === $led && $parsed->documentNumber === $number;
    }

    private static function describes(IudData $parsed, SelfBillingAuthorizationRequestData $request, Environment $repository): bool
    {
        return $parsed->repository === $repository
            && $parsed->emitterTaxId === $request->taxId
            && $parsed->documentType === $request->documentType;
    }

    /**
     * @return list<RemoteMessage>|null
     */
    private static function messages(mixed $messages): ?array
    {
        if (! \is_array($messages) || $messages === [] || ! array_is_list($messages)) {
            return null;
        }

        $parsed = array_values(array_filter(array_map(self::message(...), $messages)));

        return \count($parsed) === \count($messages) ? $parsed : null;
    }

    private static function message(mixed $message): ?RemoteMessage
    {
        if (! \is_array($message)) {
            return null;
        }

        $fields = [];
        foreach ($message as $key => $value) {
            $name = lcfirst((string) $key);
            if (! \in_array($name, self::MESSAGE_FIELDS, true) || ($value !== null && ! \is_string($value)) || \array_key_exists($name, $fields)) {
                return null;
            }

            $fields[$name] = $value === null || Str::trim($value) === '' ? null : Str::trim($value);
        }

        $source = $fields['source'] ?? null;
        $type   = $fields['type'] ?? null;
        $code   = $fields['code'] ?? null;

        return $source === null || $type === null || $code === null
            ? null
            : new RemoteMessage($source, $type, $fields['dateTime'] ?? null, $code, $fields['description'] ?? null, $fields['location'] ?? null);
    }
}
