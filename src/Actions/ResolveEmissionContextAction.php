<?php

declare(strict_types=1);

namespace Akira\Efatura\Actions;

use Akira\Efatura\Configuration\EfaturaConfig;
use Akira\Efatura\Data\EmissionContextData;
use Akira\Efatura\Enums\EmissionMode;
use Akira\Efatura\Exceptions\ConfigurationException;
use Akira\Efatura\Support\Fiscal;

final readonly class ResolveEmissionContextAction
{
    public function __construct(private EfaturaConfig $config) {}

    public function handle(?EmissionContextData $emission): EmissionContextData
    {
        if ($emission instanceof EmissionContextData) {
            return $emission;
        }

        $transmitter = self::configured($this->config->transmitter->taxId, 'efatura.transmitter.tax_id');

        return EmissionContextData::from([
            'issueMode'        => EmissionMode::Online->value,
            'transmitterTaxId' => ['value' => $transmitter, 'countryCode' => Fiscal::COUNTRY],
            'software'         => [
                'code'    => self::configured($this->config->software->code, 'efatura.software.code'),
                'name'    => self::configured($this->config->software->name, 'efatura.software.name'),
                'version' => self::configured($this->config->software->version, 'efatura.software.version'),
            ],
        ]);
    }

    private static function configured(?string $value, string $path): string
    {
        return $value ?? throw new ConfigurationException('configuration.missing', $path);
    }
}
