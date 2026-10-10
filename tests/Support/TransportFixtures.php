<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Packaging\PackagedArchive;

final class TransportFixtures
{
    public const string FIRST_IUD = 'CV3260208100200300001230100000000112345678909';

    public const string SECOND_IUD = 'CV3260208100200300001230100000000212345678907';

    public const string THIRD_IUD = 'CV3260208100200300001230100000000312345678905';

    /**
     * @param list<string> $identifiers
     */
    public static function archive(array $identifiers = [self::FIRST_IUD], PackageKind $kind = PackageKind::Documents): PackagedArchive
    {
        return new PackagedArchive('zip-bytes', $kind, array_map(static fn (string $identifier): string => $identifier . '.xml', $identifiers));
    }

    public static function response(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/http/' . $name . '.xml');
    }
}
