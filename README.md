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
use Akira\Efatura\Data\ElectronicInvoiceData;
use Akira\Efatura\Enums\DocumentType;

$document = ElectronicInvoiceData::from([
    'invoice' => [
        'type' => DocumentType::ELECTRONIC_INVOICE,
        'issueDate' => now()->toDateString(),
        'emitter' => [
            'nif' => '100200300',
            'name' => 'Emitter',
        ],
        'receiver' => [
            'nif' => '900800700',
            'name' => 'Receiver',
        ],
        'lines' => [
            [
                'description' => 'Service',
                'quantity' => 1,
                'unitPrice' => 1000,
                'total' => 1000,
                'taxes' => [
                    [
                        'type' => 'IVA',
                        'rate' => 15,
                        'amount' => 150,
                    ],
                ],
            ],
        ],
        'totals' => [
            'subtotal' => 1000,
            'taxTotal' => 150,
            'grandTotal' => 1150,
        ],
    ],
]);
```

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
