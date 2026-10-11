<?php

declare(strict_types=1);

namespace Akira\Efatura\Http\Requests;

use Akira\Efatura\Configuration\RoutesConfig;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Xml\XmlValidation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class ValidateXmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(RoutesConfig $routes): array
    {
        return [
            'file'    => ['required', 'file', 'mimetypes:application/xml,text/xml,text/plain', 'max:' . $routes->maxUploadKilobytes],
            'profile' => ['nullable', 'string', Rule::in(array_keys(XmlValidation::PROFILES))],
        ];
    }

    public function xml(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? (string) $file->get() : '';
    }

    public function profile(): ?SignatureProfile
    {
        $name = $this->validated('profile');

        return \is_string($name) ? XmlValidation::PROFILES[$name] ?? null : null;
    }

    #[Override]
    protected function failedValidation(Validator $validator): never
    {
        throw new ValidationException($validator, new JsonResponse(
            ['valid' => false, 'errors' => $validator->errors()->toArray()],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
    }
}
