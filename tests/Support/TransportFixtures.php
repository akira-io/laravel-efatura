<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Packaging\PackagedArchive;

final class TransportFixtures
{
    public const string FIRST_IUD = 'CV3260208100200300001230100000000112345678909';

    public const string SECOND_IUD = 'CV3260208100200300001230100000000212345678907';

    public const string THIRD_IUD = 'CV3260208100200300001230100000000312345678905';

    /**
     * @param list<string>      $identifiers
     * @param list<string>|null $emitters
     */
    public static function archive(
        array $identifiers = [self::FIRST_IUD],
        PackageKind $kind = PackageKind::Documents,
        Environment $repository = Environment::Test,
        ?array $emitters = null,
    ): PackagedArchive {
        $entries = array_map(static fn (string $identifier): string => $identifier . '.xml', $identifiers);
        $derived = array_values(array_unique(array_map(static fn (string $identifier): string => substr($identifier, 9, 9), $identifiers)));

        return new PackagedArchive('zip-bytes', $kind, $entries, $repository, $emitters ?? $derived);
    }

    public static function response(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/http/' . $name . '.xml');
    }
}
