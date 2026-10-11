<?php

declare(strict_types=1);

namespace Akira\Efatura\Transport;

use Akira\Efatura\Configuration\HttpClientConfig;
use Akira\Efatura\Data\SelfBillingAuthorizationRequestData;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\TransportFailure;
use Akira\Efatura\Exceptions\TransportException;
use Akira\Efatura\Money\DecimalFormatter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use SensitiveParameter;

final readonly class SelfBillingExchange
{
    private const string RESOURCE = 'dfe/self-billing/authorize';

    public function __construct(
        private TransportRequests $requests,
        private BoundedBody $body,
        private SelfBillingResponseParser $parser,
        private FailureClassifier $failures,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function post(
        HttpClientConfig $config,
        Environment $repository,
        SelfBillingAuthorizationRequestData $request,
        #[SensitiveParameter]
        array $headers,
    ): Response {
        $payload = [
            'taxId'             => $request->taxId,
            'documentTypeCode'  => $request->documentType->code(),
            'mobilePhoneNumber' => $request->mobilePhoneNumber,
            'totalAmount'       => DecimalFormatter::decimal($request->totalAmount->getAmount()),
        ];

        try {
            return $this->requests->for($config, $repository, 'application/json')->withHeaders($headers)->asJson()->post(self::RESOURCE, $payload);
        } catch (ConnectionException $connectionException) {
            throw $this->failures->classify($connectionException, null);
        }
    }

    public function authorization(
        SelfBillingAuthorizationRequestData $request,
        Environment $repository,
        #[SensitiveParameter]
        Response $response,
    ): SelfBillingAuthorization {
        $body = $this->body->answer($response, null);

        try {
            return $this->parser->parse($request, $repository, $response->status(), $body);
        } catch (TransportException $transportException) {
            throw_if($response->successful() || $transportException->failure === TransportFailure::Rejected, $transportException);

            throw TransportException::because(SubmissionStatus::failure($response->status()), status: $response->status(), bytes: \strlen($body));
        }
    }
}
