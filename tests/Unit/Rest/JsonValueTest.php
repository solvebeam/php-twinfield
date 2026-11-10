<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Rest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class JsonValueTest extends TestCase
{
    public function testReadsTypedValues(): void
    {
        $json = JsonValue::decode(<<<'JSON'
            {
                "id": "FFE2C8B3-E7F0-4793-A059-FFBFE3C154A9",
                "name": "Bike",
                "version": 3,
                "active": true,
                "netPrice": 121.1,
                "percent": 21,
                "createdAt": "2026-03-15T10:12:45.123Z",
                "translations": {"nl-NL": "Fiets"},
                "codes": ["A", "B"],
                "rates": [{"percent": 9}, {"percent": 21.5}]
            }
            JSON);

        self::assertSame('ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9', $json->uuid('id'));
        self::assertSame('Bike', $json->string('name'));
        self::assertSame(3, $json->int('version'));
        self::assertTrue($json->bool('active'));
        self::assertSame('121.10', (string) $json->amount('netPrice'));
        self::assertSame('21', $json->decimal('percent'));
        self::assertSame('2026-03-15 10:12:45', $json->dateTime('createdAt')->format('Y-m-d H:i:s'));
        self::assertSame(['nl-NL' => 'Fiets'], $json->stringMap('translations'));
        self::assertSame(['A', 'B'], $json->strings('codes'));
        self::assertSame(
            ['9', '21.5'],
            array_map(static fn (JsonValue $rate): string => $rate->decimal('percent'), $json->list('rates')),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function numbers(): iterable
    {
        yield 'integer' => ['121', '121'];
        yield 'one decimal' => ['121.1', '121.1'];
        yield 'trailing zero' => ['121.10', '121.1'];
        yield 'negative' => ['-0.01', '-0.01'];
        yield 'negative zero' => ['-0.0', '0'];
        yield 'large amount' => ['9999999999999.99', '9999999999999.99'];
        yield 'float artefact' => ['0.30000000000000004', '0.3'];
    }

    #[DataProvider('numbers')]
    public function testReadsNumbersAsExactStrings(string $number, string $expected): void
    {
        self::assertSame($expected, JsonValue::decode(sprintf('{"value": %s}', $number))->decimal('value'));
    }

    public function testReadsTimestampsAsUtcWithOrWithoutZone(): void
    {
        // The list of VAT codes leaves out the `Z` that the VAT code itself has.
        $json = JsonValue::decode(
            '{"a": "2011-02-23T21:50:21.52", "b": "2011-02-23T21:50:21.52Z", "c": "2011-02-23T22:50:21.52+01:00"}',
        );

        self::assertSame('2011-02-23T21:50:21.520000+00:00', $json->timestamp('a')->format('Y-m-d\TH:i:s.uP'));
        self::assertEquals($json->timestamp('a'), $json->timestamp('b'));
        self::assertEquals($json->timestamp('a'), $json->timestamp('c'));
    }

    public function testReadsDatesWithoutTime(): void
    {
        $json = JsonValue::decode('{"a": "2021-04-26T00:00:00Z", "b": "2021-04-26T00:00:00"}');

        self::assertEquals(new \DateTimeImmutable('2021-04-26'), $json->date('a'));
        self::assertEquals(new \DateTimeImmutable('2021-04-26'), $json->date('b'));
    }

    public function testRefusesAnAmountWithMoreThanTwoDecimals(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('netPrice is 0.125, expected an amount with at most 2 decimals.');

        JsonValue::decode('{"netPrice": 0.125}')->amount('netPrice');
    }

    public function testRefusesANumberWithExponent(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('expected a number without exponent');

        JsonValue::decode('{"value": 1e20}')->decimal('value');
    }

    public function testNamesThePathOfAMissingValue(): void
    {
        $json = JsonValue::decode('{"items": [{"id": "ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9"}, {"name": "x"}]}');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('items[1]/id is missing.');

        $json->list('items')[1]->uuid('id');
    }

    public function testRefusesNullForARequiredValue(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('name is null.');

        JsonValue::decode('{"name": null}')->string('name');
    }

    public function testNullableReadersAcceptMissingAndNull(): void
    {
        $json = JsonValue::decode('{"a": null, "empty": "", "none": "00000000-0000-0000-0000-000000000000"}');

        self::assertNull($json->nullableString('a'));
        self::assertNull($json->nullableString('empty'));
        self::assertNull($json->nullableString('missing'));
        self::assertNull($json->nullableAmount('a'));
        self::assertNull($json->nullableUuid('none'));
        self::assertNull($json->nullableObject('missing'));
        self::assertSame([], $json->list('missing'));
    }

    public function testRefusesAGuidThatIsNotOne(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('id is "1000", expected a GUID.');

        JsonValue::decode('{"id": "1000"}')->uuid('id');
    }

    public function testRefusesInvalidJson(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The response is not valid JSON');

        JsonValue::decode('<html>');
    }

    public function testRefusesAnArrayWhereAnObjectIsExpected(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The response is not a JSON object.');

        JsonValue::decode('[{"id": 1}]');
    }

    public function testDecodesAListOfObjects(): void
    {
        $items = JsonValue::decodeList('[{"code": "A"}, {"code": "B"}]');

        self::assertSame(['A', 'B'], array_map(static fn (JsonValue $item): string => $item->string('code'), $items));
        self::assertSame('[1]', $items[1]->path);
    }
}
