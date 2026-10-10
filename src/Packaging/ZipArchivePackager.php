<?php

declare(strict_types=1);

namespace Akira\Efatura\Packaging;

use Akira\Efatura\Actions\ParseEventIdAction;
use Akira\Efatura\Actions\ParseIudAction;
use Akira\Efatura\Contracts\Packager;
use Akira\Efatura\Enums\Environment;
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

        $entries    = [];
        $emitters   = [];
        $kind       = null;
        $repository = null;
        foreach ($signedXml as $position => $xml) {
            [$entryKind, $id, $entryRepository, $emitters[]] = $this->identify($xml, $position);
            $kind ??= $entryKind;
            $repository ??= $entryRepository;

            if ($entryKind !== $kind) {
                throw new PackagingException('package.mixed_kinds', ['entry' => $position]);
            }

            if ($entryRepository !== $repository) {
                throw new PackagingException('package.mixed_repositories', ['entry' => $position]);
            }

            if (isset($entries[$id . '.xml'])) {
                throw new PackagingException('package.duplicate_entry', ['entry' => $position]);
            }

            $entries[$id . '.xml'] = $xml;
        }

        return new PackagedArchive(
            $this->write($entries),
            $kind ?? PackageKind::Documents,
            array_keys($entries),
            $repository ?? Environment::Test,
            array_values(array_unique($emitters)),
        );
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
     * @return array{PackageKind, string, Environment, string}
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

        $id = (string) $root?->getAttribute('Id');

        return [$kind, $id, ...$this->origin($kind, $id, $root, $position)];
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

    /**
     * @return array{Environment, string}
     */
    private function origin(PackageKind $kind, string $id, ?DOMElement $root, int $position): array
    {
        try {
            if ($kind === PackageKind::Documents) {
                $iud = $this->parseIud->handle($id);

                return [$iud->repository, $iud->emitterTaxId];
            }

            $repository = $this->parseEventId->handle($id)->repository;
        } catch (ValidationException) {
            throw new PackagingException('package.invalid_identifier', ['entry' => $position]);
        }

        return [$repository, self::eventEmitter($root) ?? throw new PackagingException('package.missing_emitter', ['entry' => $position])];
    }

    private static function eventEmitter(?DOMElement $root): ?string
    {
        foreach ($root->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === Fiscal::XML_NAMESPACE && $child->localName === 'EmitterTaxId') {
                $taxId = Str::trim($child->textContent);

                return $taxId === '' ? null : $taxId;
            }
        }

        return null;
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
