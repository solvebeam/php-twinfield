<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Docs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Docs\ExampleXml;
use SolveBeam\Twinfield\Tests\Support\Docs\ExampleXmlDefinitions;

/**
 * The request and response XML in the example READMEs matches what the SDK sends.
 */
final class ExampleXmlTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        foreach (array_keys(ExampleXmlDefinitions::examples()) as $example) {
            yield $example => [$example];
        }
    }

    #[DataProvider('examples')]
    public function testReadmeIsUpToDate(string $example): void
    {
        $readme = (string) file_get_contents(ExampleXml::examplesDir() . '/' . $example . '/README.md');

        self::assertSame(
            ExampleXml::readme($example, $readme),
            $readme,
            sprintf('The XML in examples/%s/README.md is out of date: run `composer docs:xml`.', $example),
        );
    }

    public function testEveryExampleOfTheDocumentedPagesHasXml(): void
    {
        $missing = [];

        foreach (array_keys(ExampleXmlDefinitions::pages()) as $directory) {
            foreach (glob(ExampleXml::examplesDir() . '/' . $directory . '/*', GLOB_ONLYDIR) ?: [] as $path) {
                $example = $directory . '/' . basename($path);

                if (!isset(ExampleXmlDefinitions::examples()[$example])) {
                    $missing[] = $example;
                }
            }
        }

        self::assertSame([], $missing, 'Add these examples to ExampleXmlDefinitions.');
    }
}
