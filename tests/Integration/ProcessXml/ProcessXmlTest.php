<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\ProcessXml;

use DOMElement;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\MessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live ProcessXml requests (read-only).
 */
final class ProcessXmlTest extends IntegrationTestCase
{
    public function testListsCompaniesWithoutCompany(): void
    {
        $result = $this->twinfield->withoutCompany()->processXml('<list><type>offices</type></list>');

        self::assertSame('offices', $result->root()->localName);

        $codes = [];

        foreach ($result->root()->childNodes as $office) {
            if ($office instanceof DOMElement) {
                self::assertSame('office', $office->localName);
                self::assertTrue($office->hasAttribute('name'), 'Twinfield adds the name.');

                $codes[] = $office->textContent;
            }
        }

        self::assertContains($this->environment->config->company(), $codes);
    }

    public function testReadsADimension(): void
    {
        $company = $this->environment->config->company();
        $rows = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', 'DEB')->limit(1)->get()->rows;

        if ($rows === []) {
            self::markTestSkipped('The company has no customers.');
        }

        $code = $rows[0][0];

        $result = $this->twinfield->processXml(<<<XML
            <read>
                <type>dimensions</type>
                <office>{$company}</office>
                <dimtype>DEB</dimtype>
                <code>{$code}</code>
            </read>
            XML);

        $xpath = $result->xpath();

        self::assertSame('dimension', $result->root()->localName);
        self::assertSame($code, $xpath->evaluate('string(/dimension/code)'));
        self::assertSame($company, $xpath->evaluate('string(/dimension/office)'));
        self::assertNotSame('', $xpath->evaluate('string(/dimension/type/@name)'), 'Twinfield adds the type name.');
    }

    public function testThrowsWithTheMessageOfTheFailingElement(): void
    {
        try {
            $this->twinfield->processXml('<read><type>nonsense</type></read>');
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            $errors = $exception->result->errors();

            self::assertCount(1, $errors);
            self::assertSame(MessageType::Error, $errors[0]->type);
            self::assertSame('read/type', $errors[0]->path);
            self::assertSame('nonsense', $errors[0]->element->textContent);
        }
    }
}
