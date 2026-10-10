<?php

declare(strict_types=1);

namespace Akira\Efatura\Packaging;

use Akira\Efatura\Actions\ParseEventIdAction;
use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Enums\PackageKind;
use Akira\Efatura\Exceptions\PackagingException;
use Akira\Efatura\Support\Fiscal;
use Akira\Efatura\Xml\SafeXmlParser;
use DOMElement;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final readonly class ZipArchivePackager implements Packager
{
    private const int ENTRY_TIMESTAMP = 315_662_400;

    public function __construct(
        private SafeXmlParser $parser,
        private ParseIudAction $parseIud,
        private ParseEventIdAction $parseEventId,
        private ?string $directory = null,
    ) {}

    public function package(array $signedXml): PackagedArchive
    {
        self::guardLimits($signedXml);

        $entries = [];
        $kind    = null;
        foreach ($signedXml as $position => $xml) {
            [$entryKind, $id] = $this->identify($xml, $position);
            $kind ??= $entryKind;

            if ($entryKind !== $kind) {
                throw new PackagingException('package.mixed_kinds', ['entry' => $position]);
            }

            if (isset($entries[$id . '.xml'])) {
                throw new PackagingException('package.duplicate_entry', ['entry' => $position]);
            }

            $entries[$id . '.xml'] = $xml;
        }

        return new PackagedArchive($this->write($entries), $kind ?? PackageKind::Documents, array_keys($entries));
    }

    /**
     * @param list<string> $signedXml
     */
    private static function guardLimits(array $signedXml): void
    {
        if ($signedXml === []) {
            throw new PackagingException('package.empty');
        }

        if (\count($signedXml) > Fiscal::MAX_PACKAGE_ENTRIES) {
            throw new PackagingException('package.too_many_entries', ['limit' => Fiscal::MAX_PACKAGE_ENTRIES, 'entries' => \count($signedXml)]);
        }

        $bytes = array_sum(array_map(strlen(...), $signedXml));
        if ($bytes > Fiscal::MAX_PACKAGE_BYTES) {
            throw new PackagingException('package.too_large', ['limit' => Fiscal::MAX_PACKAGE_BYTES, 'bytes' => $bytes]);
        }
    }

    /**
     * @return array{PackageKind, string}
     */
    private function identify(string $xml, int $position): array
    {
        $outer = $this->parser->parse($xml)->documentElement;
        $root  = $outer?->localName === Fiscal::DETACHED_SIGNATURE_ROOT && $outer->namespaceURI === null ? $outer->lastElementChild : $outer;
        $kind  = match ($root?->namespaceURI === Fiscal::XML_NAMESPACE ? $root->localName : null) {
            'Dfe'   => PackageKind::Documents,
            'Event' => PackageKind::Events,
            default => throw new PackagingException('package.unsupported_root', ['entry' => $position]),
        };

        if (! $outer instanceof DOMElement || ! self::signed($outer)) {
            throw new PackagingException('package.unsigned', ['entry' => $position]);
        }

        return [$kind, $this->identifier($kind, (string) $root?->getAttribute('Id'), $position)];
    }

    private static function signed(DOMElement $outer): bool
    {
        foreach ($outer->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === Fiscal::XMLDSIG_NAMESPACE && $child->localName === 'Signature') {
                return true;
            }
        }

        return false;
    }

    private function identifier(PackageKind $kind, string $id, int $position): string
    {
        try {
            $kind === PackageKind::Documents ? $this->parseIud->handle($id) : $this->parseEventId->handle($id);
        } catch (ValidationException) {
            throw new PackagingException('package.invalid_identifier', ['entry' => $position]);
        }

        return $id;
    }

    /**
     * @param array<string, string> $entries
     */
    private function write(array $entries): string
    {
        $path = ($this->directory ?? sys_get_temp_dir()) . '/efatura-zip-' . Str::random(32) . '.zip';

        try {
            $archive = new ZipArchive;
            $opened  = @$archive->open($path, ZipArchive::CREATE | ZipArchive::EXCL) === true;
            $written = $opened && self::add($archive, $entries) && @$archive->close();
            $bytes   = $written ? file_get_contents($path) : false;

            return \is_string($bytes) ? $bytes : throw new PackagingException('package.write_failed');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param array<string, string> $entries
     */
    private static function add(ZipArchive $archive, array $entries): bool
    {
        $added = true;
        foreach ($entries as $name => $xml) {
            $added = $added
                && $archive->addFromString($name, $xml)
                && $archive->setCompressionName($name, ZipArchive::CM_DEFLATE)
                && $archive->setMtimeName($name, self::ENTRY_TIMESTAMP);
        }

        return $added;
    }
}
