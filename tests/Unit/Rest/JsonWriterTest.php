<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Rest;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Rate\UnitPrice;
use SolveBeam\Twinfield\Rest\JsonNumber;
use SolveBeam\Twinfield\Rest\JsonWriter;
use SolveBeam\Twinfield\Transaction\Amount;

final class JsonWriterTest extends TestCase
{
    public function testWritesAmountsAsExactNumbers(): void
    {
        self::assertSame(
            '{"name":"Fiets / bike","netPrice":121.10,"gross":-0.50,"price":0.1550,"percent":21.5,"lines":[{"value":1.00}],"none":null}',
            JsonWriter::encode([
                'name' => 'Fiets / bike',
                'netPrice' => Amount::of('121.1'),
                'gross' => Amount::of('-0.5'),
                'price' => UnitPrice::of('0.155'),
                'percent' => JsonNumber::of('21.5'),
                'lines' => [['value' => Amount::of('1')]],
                'none' => null,
            ]),
        );
    }

    public function testWritesAnEmptyObject(): void
    {
        self::assertSame('{"translations":{}}', JsonWriter::encode(['translations' => (object) []]));
    }

    public function testRefusesFloats(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body value lines/0/value is a float');

        JsonWriter::encode(['lines' => [['value' => 1.5]]]);
    }

    public function testRefusesObjectsItCantSend(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body value date is a DateTimeImmutable');

        JsonWriter::encode(['date' => new DateTimeImmutable()]);
    }

    public function testJsonNumberRefusesNonNumbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonNumber::of('1e3');
    }
}
