<?php

declare(strict_types=1);

namespace Akira\Efatura\Tests\Support;

use Akira\Efatura\Contracts\SchemaValidator;
use Akira\Efatura\Enums\DocumentType;
use Akira\Efatura\Exceptions\SchemaValidationException;
use Akira\Efatura\Support\OfficialArtifacts;
use Akira\Efatura\Xml\LibxmlSchemaValidator;
use DOMDocument;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

final readonly class SchemaFixtures
{
    public const string XSD = 'xsd/efatura/2024-05-27/';

    public string $root;

    private Filesystem $files;

    public function __construct()
    {
        $this->files = new Filesystem;
        $this->root  = realpath(sys_get_temp_dir()) . '/efatura-schemas-' . bin2hex(random_bytes(12));
        $this->files->ensureDirectoryExists($this->root);
    }

    public static function resources(): string
    {
        return (string) realpath(__DIR__ . '/../../resources');
    }

    public static function official(string $artifact): string
    {
        return (string) file_get_contents(resolve(OfficialArtifacts::class)->path(self::XSD . $artifact));
    }

    public static function withoutSignature(string $xml): string
    {
        $document = new DOMDocument;
        $document->loadXML($xml);
        foreach (iterator_to_array($document->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')) as $signature) {
            $signature->parentNode?->removeChild($signature);
        }

        return (string) $document->saveXML();
    }

    public static function invalidInvoice(): string
    {
        return str_replace('<Serie>A-1</Serie>', '<Serie>A 1</Serie>', DocumentXmlGraphs::fixture(DocumentType::Invoice));
    }

    public static function rejection(string $xml): SchemaValidationException
    {
        try {
            resolve(SchemaValidator::class)->validate($xml);
        } catch (SchemaValidationException $schemaValidationException) {
            return $schemaValidationException;
        }

        throw new RuntimeException('The document was expected to be rejected.');
    }

    public function copyOfficial(): self
    {
        $this->files->copyDirectory(self::resources() . '/xsd', $this->root . '/xsd');
        $this->files->copy(self::resources() . '/official-artifacts.json', $this->root . '/official-artifacts.json');

        return $this;
    }

    /**
     * @param array<string, string> $schemas
     */
    public function custom(array $schemas): self
    {
        $records = [];
        foreach ($schemas as $artifact => $contents) {
            $this->files->ensureDirectoryExists(\dirname($this->root . '/' . $artifact));
            $this->files->put($this->root . '/' . $artifact, $contents);
            $records[$artifact] = ['sha256' => hash('sha256', $contents), 'size' => \strlen($contents)];
        }

        $this->files->put($this->root . '/official-artifacts.json', json_encode([
            'signature_profiles' => ['EnvelopedSignature' => array_key_first($schemas)],
            'files'              => $records,
        ], JSON_THROW_ON_ERROR));

        return $this;
    }

    public function write(string $artifact, string $contents): void
    {
        $this->files->ensureDirectoryExists(\dirname($this->root . '/' . $artifact));
        $this->files->put($this->root . '/' . $artifact, $contents);
    }

    public function validator(): LibxmlSchemaValidator
    {
        return new LibxmlSchemaValidator(new OfficialArtifacts($this->files, $this->root));
    }

    public function remove(): void
    {
        $this->files->deleteDirectory($this->root);
    }
}
