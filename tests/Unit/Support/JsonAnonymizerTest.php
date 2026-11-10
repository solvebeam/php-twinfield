<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Fixtures\JsonAnonymizer;
use SolveBeam\Twinfield\Tests\Support\Fixtures\XmlAnonymizer;

final class JsonAnonymizerTest extends TestCase
{
    private const GUID = 'ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9';

    public function testKeepsTheStructureAndFormatsButNotTheValues(): void
    {
        $json = sprintf(
            '{"id":"%1$s","name":"Fiets 10:30, [12]","netPrice":1234.50,"version":3,"status":"active",'
            . '"createdAt":"2025-03-01T10:00:00Z","translations":{},"items":[{"code":"BIKE"}],"none":null,'
            . '"_links":{"self":{"href":"https://api.accounting.twinfield.com/Api/companies/%1$s/x"}}}',
            self::GUID,
        );

        $output = $this->anonymizer()->anonymize($json);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($result);
        self::assertMatchesRegularExpression(
            '~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~',
            $result['id'],
        );
        self::assertNotSame(self::GUID, $result['id']);
        self::assertNotSame('Fiets 10:30, [12]', $result['name']);
        self::assertSame(3, $result['version']);
        self::assertSame('active', $result['status']);
        self::assertNotSame('2025-03-01T10:00:00Z', $result['createdAt']);
        self::assertMatchesRegularExpression('~^\d{4}-\d{2}-\d{2}T10:00:00Z$~', $result['createdAt']);
        self::assertStringContainsString('"translations": {}', $output, 'An empty object stays an object.');
        self::assertNull($result['none']);
        self::assertSame(
            'https://example.test/Api/companies/' . $result['id'] . '/x',
            $result['_links']['self']['href'],
        );
    }

    public function testKeepsTheDigitsOfNumbers(): void
    {
        $output = $this->anonymizer()->anonymize('{"netPrice":1234.50}');

        self::assertMatchesRegularExpression('~"netPrice": \d{4}\.\d0\n~', $output);
    }

    public function testNeverWritesALeadingZero(): void
    {
        for ($number = 100; $number < 400; $number++) {
            $output = $this->anonymizer()->anonymize(sprintf('{"value":%d.41}', $number));

            self::assertIsObject(json_decode($output, false, 512, JSON_THROW_ON_ERROR));
        }
    }

    public function testIsDeterministic(): void
    {
        $json = '{"code":"BIKE","price":99.95}';

        self::assertSame($this->anonymizer()->anonymize($json), $this->anonymizer()->anonymize($json));
    }

    private function anonymizer(): JsonAnonymizer
    {
        return new JsonAnonymizer(new XmlAnonymizer(str_repeat('k', 32)));
    }
}
