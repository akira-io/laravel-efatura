<?php

declare(strict_types=1);

use Akira\Efatura\Rules\ValidTaxId;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\artisan;

function packagedTranslations(string $locale): array
{
    return Arr::dot(require __DIR__ . '/../resources/lang/' . $locale . '/efatura.php');
}

function translationPlaceholders(string $line): array
{
    preg_match_all('/:([a-z_]+)/', $line, $matches);

    return collect($matches[1])->unique()->sort()->values()->all();
}

it('ships every english key in portuguese and nothing else', function (): void {
    $english    = array_keys(packagedTranslations('en'));
    $portuguese = array_keys(packagedTranslations('pt'));

    expect(array_values(array_diff($english, $portuguese)))->toBe([])
        ->and(array_values(array_diff($portuguese, $english)))->toBe([])
        ->and($portuguese)->toBe($english);
});

it('keeps the placeholders of every message', function (string $key): void {
    $portuguese = packagedTranslations('pt')[$key] ?? null;

    expect($portuguese)->toBeString()->not->toBe('')
        ->and(translationPlaceholders((string) $portuguese))->toBe(translationPlaceholders(packagedTranslations('en')[$key]));
})->with(fn (): array => array_keys(packagedTranslations('en')));

it('translates every message', function (string $key): void {
    expect(packagedTranslations('pt')[$key])->not->toBe(packagedTranslations('en')[$key]);
})->with(fn (): array => array_keys(packagedTranslations('en')));

it('uses the fiscal terminology of the manual', function (): void {
    $portuguese = implode(PHP_EOL, packagedTranslations('pt'));

    expect($portuguese)->toContain('NIF')
        ->toContain('IUD')
        ->toContain('DFE')
        ->toContain('Documento Fiscal Auxiliar')
        ->not->toContain('tax identifier');
});

it('renders a validation message in portuguese when the locale is pt', function (): void {
    app()->setLocale('pt');

    $validator = Validator::make(['tax_id' => '123'], ['tax_id' => [new ValidTaxId('CV')]]);

    expect($validator->errors()->first('tax_id'))->toBe('O campo tax id tem de ser um NIF válido para o seu país.');
});

it('renders a console message in portuguese when the locale is pt', function (): void {
    app()->setLocale('pt');
    $path = sys_get_temp_dir() . '/efatura-missing-' . uniqid() . '.xml';

    artisan('efatura:validate', ['path' => $path])
        ->expectsOutputToContain('O ficheiro ' . $path . ' não existe.')
        ->assertExitCode(1);
});

it('calls the iud check digit a digito verificador in portuguese', function (): void {
    expect(packagedTranslations('pt')['validation.iud_invalid'])
        ->toBe('O campo :attribute tem de ser um IUD oficial com dígito verificador válido.')
        ->and(collect(packagedTranslations('pt'))->filter(fn (string $line): bool => str_contains($line, 'dígito de controlo'))->all())->toBe([]);
});

it('leads the hidden violations message with the count in portuguese', function (): void {
    app()->setLocale('pt');

    expect(trans('efatura::efatura.console.validate.violations_hidden', ['count' => 50]))->toBe('Mais 50 violações não são mostradas.');
});
