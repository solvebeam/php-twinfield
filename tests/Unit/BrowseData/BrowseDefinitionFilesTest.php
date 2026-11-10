<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BrowseData;

use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\BrowseData\BrowseDefinition;
use SolveBeam\Twinfield\BrowseData\BrowseField;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

/**
 * The definitions in browse-definitions/ (saved with `composer twinfield:browse-definitions`) are
 * read with the models of the SDK, so a change by Twinfield shows up here.
 */
final class BrowseDefinitionFilesTest extends TestCase
{
    private const string DIR = __DIR__ . '/../../../browse-definitions';

    /**
     * @return array<string, array{BrowseCode}>
     */
    public static function codes(): array
    {
        $codes = [];

        foreach (BrowseCode::cases() as $code) {
            $codes['code ' . $code->value] = [$code];
        }

        return $codes;
    }

    #[DataProvider('codes')]
    public function testReadsTheDefinitionOfEachBrowseCode(BrowseCode $code): void
    {
        $file = sprintf('%s/%s.xml', self::DIR, $code->value);

        self::assertFileExists($file, 'Run composer twinfield:browse-definitions.');

        $result = ProcessXmlResult::fromXml((string) file_get_contents($file));
        $definition = BrowseDefinition::fromXml(XmlElement::root($result), TestTokens::company('1000'));

        self::assertSame($code->value, $definition->code);
        self::assertNotSame([], $definition->columns);
    }

    public function testHasNoOtherDefinitions(): void
    {
        $files = array_map(basename(...), glob(self::DIR . '/*.xml') ?: []);
        $expected = array_map(static fn (BrowseCode $code): string => $code->value . '.xml', BrowseCode::cases());

        self::assertEqualsCanonicalizing([...$expected, 'browsefields.xml'], $files);
    }

    public function testReadsTheBrowseFields(): void
    {
        $document = new DOMDocument();
        $document->loadXML((string) file_get_contents(self::DIR . '/browsefields.xml'));

        self::assertNotNull($document->documentElement);

        $fields = array_map(
            BrowseField::fromXml(...),
            (new XmlElement($document->documentElement, 'browsefields'))->list('browsefield'),
        );

        self::assertGreaterThan(100, count($fields));
    }
}
