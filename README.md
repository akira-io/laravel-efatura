<p align="center">
  <img src="assets/banner.svg" alt="Laravel Efatura" />
</p>

<p align="center">
  <a href="https://packagist.org/packages/akira/efatura"><img src="https://img.shields.io/packagist/v/akira/efatura.svg" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/akira/efatura"><img src="https://img.shields.io/packagist/dt/akira/efatura.svg" alt="downloads"></a>
  <a href="https://github.com/akira-io/laravel-efatura/actions/workflows/tests.yml"><img src="https://github.com/akira-io/laravel-efatura/actions/workflows/tests.yml/badge.svg" alt="tests"></a>
  <img src="https://img.shields.io/packagist/l/akira/efatura.svg" alt="license">
  <img src="https://img.shields.io/packagist/php-v/akira/efatura" alt="php">
</p>

Laravel package for Cabo Verde e-Fatura fiscal data, configuration, and validation.

## Install

```sh
composer require akira/efatura
```

```json
{
  "require": {
    "akira/efatura": "^1.0"
  }
}
```

Publish the package configuration and prepare the required environment keys:

```sh
php artisan efatura:install
```

## Quick Start

```php
use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Facades\Efatura;

$emitter = PartyData::from([
    'taxId' => ['value' => '100200300', 'countryCode' => 'CV'],
    'name' => 'Example emitter',
    'address' => [
        'countryCode' => 'CV', 'addressDetail' => 'Praia office',
        'addressCode' => 'CV111111111011110101',
    ],
    'contacts' => ['email' => 'billing@example.cv', 'telephone' => '2600000'],
]);

$document = Efatura::invoice()
    ->type(DocumentType::Invoice)
    ->emitter($emitter, ledCode: 1)
    ->receiver(PartyData::from([
        'taxId' => ['value' => '900800700', 'countryCode' => 'CV'],
        'name' => 'Example receiver',
    ]))
    ->line(LineItemData::from([
        'quantity' => ['value' => '1', 'unitCode' => 'C62'],
        'item' => ['description' => 'Service', 'emitterIdentification' => 'SERVICE-1'],
        'price' => '100', 'priceExtension' => '100', 'netTotal' => '100',
        'taxes' => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']],
    ]))
    ->totals(TotalsData::from([
        'priceExtensionTotalAmount' => '100', 'netTotalAmount' => '100',
        'taxTotalAmount' => '15', 'payableAmount' => '115',
    ]))
    ->build(); // ElectronicInvoiceData; no issuance or sequence allocation.
```

Replace the example identities, address and LED with your registered fiscal data.
The builder snapshots the package clock (Atlantic/Cape_Verde) when created; use
`issuedAt(CarbonInterface $dateTime)` (converted to Cabo Verde time) or `header(DocumentHeaderData $header)` for
explicit dates. Decimal strings avoid float rounding. A configured complete emitter
can replace the explicit `emitter()` call. See [builders and configuration](docs/builders.md)
and the [migration guide](docs/migration.md).

## Documentation

- [Documentation index](docs/README.md)
- Configuration: [config/efatura.php](config/efatura.php)
- API reference: [source API](https://github.com/akira-io/laravel-efatura/tree/main/src)

## Testing

```sh
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently. Releases use conventional commits, and `git-cliff` generates the changelog when a version tag is pushed.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution details.

## Security

Review [SECURITY.md](SECURITY.md) to report security vulnerabilities.

## Credits

- [kidiatoliny](https://github.com/kidiatoliny)
- [All Contributors](https://github.com/akira-io/laravel-efatura/graphs/contributors)

## License

Dual-licensed under either of the following, at your option:

- MIT License ([LICENSE-MIT](LICENSE-MIT) or https://opensource.org/licenses/MIT)
- Apache License 2.0 ([LICENSE-APACHE](LICENSE-APACHE) or https://www.apache.org/licenses/LICENSE-2.0)

Unless you explicitly state otherwise, any contribution intentionally submitted for inclusion in this project by you, as defined in the Apache-2.0 license, shall be dual-licensed as above, without extra terms or conditions.
