<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\DocumentData;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Support\Fiscal;

final readonly class ResolveEmissionContextAction
{
    public function __construct(private EfaturaConfig $config) {}

    /**
     * @template TData of DocumentData|EventData
     *
     * @param  TData $data
     * @return TData
     */
    public function handle(DocumentData|EventData $data): DocumentData|EventData
    {
        if ($data->emission instanceof EmissionContextData) {
            return $data;
        }

        $transmitter         = self::configured($this->config->transmitter->taxId, 'efatura.transmitter.tax_id');
        $payload             = $data->toPayload();
        $payload['emission'] = [
            'issueMode'        => EmissionMode::Online->value,
            'transmitterTaxId' => ['value' => $transmitter, 'countryCode' => Fiscal::COUNTRY],
            'software'         => [
                'code'    => self::configured($this->config->software->code, 'efatura.software.code'),
                'name'    => self::configured($this->config->software->name, 'efatura.software.name'),
                'version' => self::configured($this->config->software->version, 'efatura.software.version'),
            ],
        ];

        return $data::from($payload);
    }

    private static function configured(?string $value, string $path): string
    {
        return $value ?? throw new ConfigurationException('configuration.missing', $path);
    }
}
