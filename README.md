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

Publish the package configuration and the sequence migration, and prepare the
required environment keys:

```sh
php artisan efatura:install
php artisan migrate
```

Run without options in a terminal, the command asks before adding each missing
`EFATURA_*` key to `.env`; with `--no-interaction` it leaves `.env` alone, and
`--write-env` appends the keys without asking. See
[installation](docs/01-installation.md) for the requirements and the optional
DFA renderers, [configuration](docs/02-configuration.md) for every key, and
[console commands](docs/15-console.md) for every option and exit code.

## Quick Start

```php
<?php

declare(strict_types=1);

use Akira\Efatura\Data\LineItemData;
use Akira\Efatura\Data\PartyData;
use Akira\Efatura\Data\TotalsData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Facades\Efatura;

$emitter = PartyData::from([
    'taxId'   => ['value' => '100200300', 'countryCode' => 'CV'],
    'name'    => 'Example emitter',
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
        'name'  => 'Example receiver',
    ]))
    ->line(LineItemData::from([
        'quantity' => ['value' => '1', 'unitCode' => 'C62'],
        'item'     => ['description' => 'Service', 'emitterIdentification' => 'SERVICE-1'],
        'price'    => '100', 'priceExtension' => '100', 'netTotal' => '100',
        'taxes'    => [['taxTypeCode' => 'IVA', 'taxPercentage' => '15']],
    ]))
    ->totals(TotalsData::from([
        'priceExtensionTotalAmount' => '100', 'netTotalAmount' => '100',
        'taxTotalAmount'            => '15', 'payableAmount' => '115',
    ]))
    ->build();
```

The example lives in [docs/examples/quick-start.php](docs/examples/quick-start.php). `build()` returns an
`ElectronicInvoiceData`; it does not issue the document or allocate a sequence number.
Replace the example identities, address and LED with your registered fiscal data.
The builder snapshots the package clock (Atlantic/Cape_Verde) when created; use
`issuedAt(CarbonInterface $dateTime)` (converted to Cabo Verde time) or `header(DocumentHeaderData $header)` for
explicit dates. Decimal strings avoid float rounding. A configured complete emitter
can replace the explicit `emitter()` call. See [builders and configuration](docs/04-builders.md)
and the [migration guide](docs/19-migration.md). Payload keys keep the official XML names
(`serie`, `taxTypeCode`, `lineTypeCode`); the Data properties that read them are
named after their concept (`$header->series`, `$tax->taxType`, `$line->lineType`),
as listed in the [renamed symbols](docs/19-migration.md#renamed-symbols) table.

To issue it, check the installation once and send the document through the
configured driver (the local middleware by default):

```php
resolve(CheckReadinessAction::class)->handle()->ready();

$issued = resolve(IssueDocumentAction::class)->handle($document);
$issued->authorized();
```

`IssueDocumentAction` numbers, signs, packages and submits the document and
dispatches Laravel events on the way. An ambiguous answer throws
`transport.outcome_unknown`: reconcile it by IUD before anything else. See
[issuance](docs/10-issuance.md), [transport](docs/09-transport.md) and
[readiness](docs/11-readiness.md).

## Documentation

- [Documentation index](docs/00-index.md)
- [Installation](docs/01-installation.md)
- [Configuration](docs/02-configuration.md)
- [Fiscal domain](docs/03-fiscal-domain.md)
- [Builders](docs/04-builders.md)
- [Identifiers, XML and schema validation](docs/05-xml.md)
- [Document numbers and sequences](docs/06-sequences.md)
- [Signing](docs/07-signing.md)
- [Packaging](docs/08-packaging.md)
- [Transport](docs/09-transport.md)
- [Issuance](docs/10-issuance.md)
- [Readiness](docs/11-readiness.md)
- [Currency conversion](docs/12-currency.md)
- [Documento Fiscal Auxiliar (DFA)](docs/13-dfa.md)
- [Artifact storage](docs/14-storage.md)
- [Console commands](docs/15-console.md)
- [Diagnostics routes](docs/16-http.md)
- [Troubleshooting](docs/17-troubleshooting.md)
- [Compliance with Manual v11](docs/18-compliance-manual-v11.md)
- [Migration guide](docs/19-migration.md)
- Configuration source: [config/efatura.php](config/efatura.php)
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
