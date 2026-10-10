<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Actions\BuildEventXmlAction;
use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Data\EventData;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Enums\Environment;
use Akira\Efatura\Enums\SignatureProfile;
use Akira\Efatura\Packaging\ZipArchivePackager;
use Akira\Efatura\Tests\Support\SignatureFixtures as S;
use Akira\Efatura\Tests\Support\XmlFixtures as X;
use Carbon\CarbonImmutable;
use Closure;
use ZipArchive;

final class PackageFixtures
{
    public static function document(int $number = 1, SignatureProfile $profile = SignatureProfile::Enveloped): string
    {
        CarbonImmutable::setTestNow(DocumentXmlGraphs::NOW);

        return S::sign(X::documentXml(X::minimal(DocumentType::Invoice)->withDocumentNumber($number)), $profile)->xml;
    }

    public static function event(string $issueDateTime = '2026-10-02T12:00:00', SignatureProfile $profile = SignatureProfile::Enveloped): string
    {
        CarbonImmutable::setTestNow(DocumentXmlGraphs::NOW);
        $eventId = EventFixtures::eventId(['issueDateTime' => $issueDateTime]);
        $event   = EventData::from(EventFixtures::transmitted(['iuds' => [EventFixtures::iud()], 'issueDateTime' => $issueDateTime]));
        $xml     = resolve(BuildEventXmlAction::class)->handle($event, $eventId, Environment::Test);

        return S::sign($xml, $profile)->xml;
    }

    public static function id(string $signedXml): string
    {
        $outer = S::document($signedXml)->documentElement;
        $root  = $outer?->localName === 'internally-detached' ? $outer->lastElementChild : $outer;

        return (string) $root?->getAttribute('Id');
    }

    public static function withId(string $signedXml, Closure $id): string
    {
        $current = self::id($signedXml);

        return str_replace('Id="' . $current . '"', 'Id="' . $id($current) . '"', $signedXml);
    }

    public static function padded(string $xml, int $bytes): string
    {
        $chunks = intdiv($bytes - \strlen($xml), 1000) - 1;
        $last   = $bytes - \strlen($xml) - $chunks * 1000;

        return $xml . str_repeat(self::comment(1000), $chunks) . self::comment($last);
    }

    public static function packager(?string $directory = null): Packager
    {
        return $directory === null ? resolve(Packager::class) : resolve(ZipArchivePackager::class, ['directory' => $directory]);
    }

    /**
     * @return list<array{name: string, method: int, crc: int, contents: string}>
     */
    public static function entries(string $bytes, string $directory): array
    {
        $path = $directory . '/archive.zip';
        file_put_contents($path, $bytes);
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::RDONLY);

        $entries = [];
        for ($index = 0; $index < $archive->count(); $index++) {
            $stat      = (array) $archive->statIndex($index);
            $entries[] = [
                'name'     => (string) $stat['name'],
                'method'   => (int) $stat['comp_method'],
                'crc'      => (int) $stat['crc'],
                'contents' => (string) $archive->getFromIndex($index),
            ];
        }

        $archive->close();
        unlink($path);

        return $entries;
    }

    private static function comment(int $bytes): string
    {
        return '<!--' . str_repeat('a', $bytes - 7) . '-->';
    }
}
