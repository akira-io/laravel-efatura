<?php

declare(strict_types=1);

namespace Akira\Efatura\Http\Controllers;

use Akira\Efatura\Actions\ValidateXmlDocumentAction;
use Akira\Efatura\Exceptions\OfficialArtifactException;
use Akira\Efatura\Http\Requests\ValidateXmlRequest;
use Akira\Efatura\Http\Resources\XmlValidationResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ValidationController
{
    public function __invoke(ValidateXmlRequest $request, ValidateXmlDocumentAction $validate): JsonResponse
    {
        try {
            $validation = $validate->handle($request->xml(), $request->profile());
        } catch (OfficialArtifactException $officialArtifactException) {
            return new JsonResponse(
                ['valid' => false, 'error' => 'artifacts_unavailable', 'code' => $officialArtifactException->errorCode],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new XmlValidationResource($validation)->response()
            ->setStatusCode($validation->valid() ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
