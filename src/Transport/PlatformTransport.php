<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Contracts\AccessTokenProvider;
use Akira\Efatura\Contracts\Transport;
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

    public function __construct(private TransportExchange $exchange, private AccessTokenProvider $tokens, private EfaturaConfig $config) {}

    public function submit(PackagedArchive $archive): SubmissionResult
    {
        throw_unless(\count($archive->emitters) === 1, TransportException::because(TransportFailure::MixedEmitters, $archive));

        $scope    = $archive->kind === PackageKind::Documents ? OAuthScope::DfeCreate : OAuthScope::EventCreate;
        $response = $this->authorized(
            $archive->emitters[0],
            [$scope],
            fn (array $headers): Response => $this->exchange->post($this->config->http->platform, $archive, $headers),
        );

        return $this->exchange->submission($archive, $response);
    }

    public function lookupDocument(string $iud): DocumentLookup
    {
        $response = $this->authorized(
            $this->exchange->document($iud)->emitterTaxId,
            [OAuthScope::DfeReadIud],
            fn (array $headers): Response => $this->exchange->fetch($this->config->http->platform, $iud, $headers),
        );

        return $this->exchange->lookup($iud, $response);
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
