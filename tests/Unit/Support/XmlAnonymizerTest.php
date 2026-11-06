<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Fixtures\XmlAnonymizer;

/**
 * The anonymizer decides what may end up in Git, so it gets its own tests.
 */
final class XmlAnonymizerTest extends TestCase
{
    private const KEY = 'test-key-0123456789abcdef';

    public function testReplacesDataButKeepsStructure(): void
    {
        $xml = $this->anonymize('finder-search-dim');

        self::assertStringNotContainsString('Voorbeeld BV', $xml);
        self::assertStringNotContainsString('>1000<', $xml);
        self::assertStringContainsString('<TotalRows>2</TotalRows>', $xml, 'Counts are kept.');
        self::assertStringContainsString('<string>Code</string>', $xml, 'Column headers are kept.');
        self::assertSame($this->elementNames($this->fixture('finder-search-dim')), $this->elementNames($xml));
    }

    public function testPreservesFormat(): void
    {
        $xml = (new XmlAnonymizer(self::KEY))->anonymize('<r><a>Voorbeeld BV</a><b>1000</b><c>-1234.56</c></r>');

        self::assertMatchesRegularExpression('~<a>[A-Z][a-z]{8} [A-Z]{2}</a>~', $xml);
        self::assertMatchesRegularExpression('~<b>\d{4}</b>~', $xml);
        self::assertMatchesRegularExpression('~<c>-\d{4}\.\d{2}</c>~', $xml);
    }

    public function testIsDeterministicPerKey(): void
    {
        $input = '<r><a>Acme</a><b>Acme</b></r>';

        $first = (new XmlAnonymizer(self::KEY))->anonymize($input);
        $second = (new XmlAnonymizer(self::KEY))->anonymize($input);
        $otherKey = (new XmlAnonymizer('another-key-0123456789'))->anonymize($input);

        self::assertSame($first, $second);
        self::assertNotSame($first, $otherKey);
        self::assertMatchesRegularExpression('~<a>(\w+)</a><b>\1</b>~', $first, 'Equal values stay equal.');
    }

    public function testShiftsDatesConsistently(): void
    {
        $xml = (new XmlAnonymizer(self::KEY))->anonymize('<r><a>20250131</a><b>2025-02-10</b><c>2025-02-10T12:00:00</c></r>');

        preg_match('~<a>(\d{8})</a><b>([\d-]{10})</b><c>([\d-]{10})T12:00:00</c>~', $xml, $matches);

        self::assertCount(4, $matches, $xml);
        self::assertNotSame('20250131', $matches[1]);

        $a = \DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);
        $b = \DateTimeImmutable::createFromFormat('!Y-m-d', $matches[2]);

        self::assertNotFalse($a);
        self::assertNotFalse($b);
        self::assertSame(10, (int) $a->diff($b)->days, 'Intervals between dates are kept.');
        self::assertSame($matches[2], $matches[3]);
    }

    public function testAlwaysReplacesSecrets(): void
    {
        $xml = (new XmlAnonymizer(self::KEY))->anonymize(
            '<r><AccessToken>abc123</AccessToken><CompanyCode>10</CompanyCode><clusterUrl>https://api.accounting.twinfield.com</clusterUrl></r>',
        );

        self::assertStringNotContainsString('abc123', $xml);
        self::assertStringNotContainsString('<CompanyCode>10<', $xml);
        self::assertStringContainsString('https://example.test', $xml);
    }

    public function testAnonymizesEmbeddedXmlAndAttributes(): void
    {
        $inner = htmlspecialchars('<dimension status="active"><name>Voorbeeld BV</name><office code="1002" /></dimension>', ENT_XML1);

        $xml = (new XmlAnonymizer(self::KEY))->anonymize("<r><ProcessXmlStringResult>{$inner}</ProcessXmlStringResult></r>");
        $result = html_entity_decode($xml, ENT_XML1);

        self::assertStringNotContainsString('Voorbeeld', $result);
        self::assertStringNotContainsString('"1002"', $result);
        self::assertStringContainsString('status="active"', $result);
    }

    private function anonymize(string $fixture): string
    {
        return (new XmlAnonymizer(self::KEY))->anonymize($this->fixture($fixture));
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/soap/' . $name . '.xml');
    }

    /**
     * @return list<string>
     */
    private function elementNames(string $xml): array
    {
        $document = new DOMDocument();
        $document->loadXML($xml);

        $names = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            $names[] = $element->nodeName;
        }

        return $names;
    }
}
