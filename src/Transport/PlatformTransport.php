<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
use Akira\Efatura\Data\SelfBillingAuthorizationRequestData;
use Akira\Efatura\Enums\OAuthScope;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Packaging\PackagedArchive;
use Closure;
use Illuminate\Http\Client\Response;
use SensitiveParameter;

final readonly class PlatformTransport implements Transport
{
    private const int UNAUTHORIZED = 401;

    public function __construct(
        private TransportExchange $exchange,
        private SelfBillingExchange $selfBilling,
        private AccessTokenProvider $tokens,
        private EfaturaConfig $config,
    ) {}

    public function submit(PackagedArchive $archive): SubmissionResult
    {
        $scope    = $archive->kind === PackageKind::Documents ? OAuthScope::DfeCreate : OAuthScope::EventCreate;
        $response = $this->authorized(
            $archive->selfBilled ? $this->buyer($archive) : $this->emitter($archive),
            [$scope],
            fn (array $headers): Response => $this->exchange->post($this->config->http->platform, $archive, $headers),
        );

        return $this->exchange->submission($archive, $response);
    }

    public function lookupDocument(string $iud, bool $selfBilled = false): DocumentLookup
    {
        $response = $this->authorized(
            $selfBilled ? $this->buyer($iud) : $this->exchange->document($iud)->emitterTaxId,
            [OAuthScope::DfeReadIud],
            fn (array $headers): Response => $this->exchange->fetch($this->config->http->platform, $iud, $headers),
        );

        return $this->exchange->lookup($iud, $response);
    }

    public function authorizeSelfBilling(SelfBillingAuthorizationRequestData $request): SelfBillingAuthorization
    {
        $repository = $this->config->environment->environment;
        $response   = $this->authorized(
            $this->buyer(null),
            [OAuthScope::DfeSelfBillingAuthorize],
            fn (array $headers): Response => $this->selfBilling->post($this->config->http->platform, $repository, $request, $headers),
        );

        return $this->selfBilling->authorization($request, $repository, $response);
    }

    private function emitter(PackagedArchive $archive): string
    {
        throw_unless(\count($archive->emitters) === 1, TransportException::because(TransportFailure::MixedEmitters, $archive));

        return $archive->emitters[0];
    }

    private function buyer(PackagedArchive|string|null $subject): string
    {
        return $this->config->emitter->taxId
            ?? throw TransportException::because(TransportFailure::MissingCredentials, $subject, field: 'efatura.emitter.tax_id');
    }

    /**
     * @param list<OAuthScope>                         $scopes
     * @param Closure(array<string, string>): Response $send
     */
    private function authorized(string $emitterTaxId, array $scopes, #[SensitiveParameter] Closure $send): Response
    {
        $response = $send($this->bearer($emitterTaxId, $scopes));
        if ($response->status() !== self::UNAUTHORIZED) {
            return $response;
        }

        $this->tokens->forget($emitterTaxId, $scopes);

        return $send($this->bearer($emitterTaxId, $scopes));
    }

    /**
     * @param  list<OAuthScope>      $scopes
     * @return array<string, string>
     */
    private function bearer(string $emitterTaxId, array $scopes): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokens->token($emitterTaxId, $scopes)->value];
    }
}
