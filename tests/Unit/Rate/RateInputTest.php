<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Rate;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Rate\RateInput;
use SolveBeam\Twinfield\Rate\RatePrice;
use SolveBeam\Twinfield\Rate\RateStatus;
use SolveBeam\Twinfield\Rate\RateType;
use SolveBeam\Twinfield\Rate\UnitPrice;

final class RateInputTest extends TestCase
{
    public function testBuildsANewRate(): void
    {
        $input = RateInput::quantity('KM', 'Kilometres')
            ->shortName('km')
            ->unit(10)
            ->currency('EUR')
            ->price(new DateTimeImmutable('2026-01-01 14:30'), internal: '0.15', external: UnitPrice::of('0.23'))
            ->price(new DateTimeImmutable('2025-01-01'), '0.155', '0.2', new DateTimeImmutable('2025-12-31'));

        self::assertSame(RateType::Quantity, $input->type);
        self::assertSame(
            '<projectrate><office>1000</office><code>KM</code><name>Kilometres</name><shortname>km</shortname>'
            . '<type>quantity</type><unit>10</unit><currency>EUR</currency><ratechanges>'
            . '<ratechange><begindate>20250101</begindate><enddate>20251231</enddate>'
            . '<internalrate>0.1550</internalrate><externalrate>0.2000</externalrate></ratechange>'
            . '<ratechange><begindate>20260101</begindate><enddate>29991231</enddate>'
            . '<internalrate>0.1500</internalrate><externalrate>0.2300</externalrate></ratechange>'
            . '</ratechanges></projectrate>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testIsImmutable(): void
    {
        $input = RateInput::time('CONSULT', 'Consultancy');
        $changed = $input->name('Advice')->type(RateType::Quantity)->unit(8)->currency('USD')->shortName('Adv');

        self::assertSame('Consultancy', $input->name);
        self::assertSame(RateType::Time, $input->type);
        self::assertSame(1, $input->unit, 'The unit is 1 by default.');
        self::assertNull($input->currency);
        self::assertNull($input->shortName);

        self::assertSame('Advice', $changed->name);
        self::assertSame(RateType::Quantity, $changed->type);
        self::assertSame(8, $changed->unit);
        self::assertSame('USD', $changed->currency);
        self::assertNull($changed->shortName('')->shortName, 'An empty short name clears it.');
    }

    public function testAPriceWithTheSameStartDateReplacesTheOtherAndKeepsItsId(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->existingPrice($this->existing(7, '2025-01-01', null, '55', '95'))
            ->price(new DateTimeImmutable('2025-01-01'), '56', '96')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString(
            '<ratechanges><ratechange id="7"><begindate>20250101</begindate><enddate>29991231</enddate>'
            . '<internalrate>56.0000</internalrate><externalrate>96.0000</externalrate></ratechange></ratechanges>',
            $xml,
        );
    }

    public function testNewPriceFromEndsTheOpenPrice(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->existingPrice($this->existing(1, '2024-01-01', '2024-12-31', '50', '90'))
            ->existingPrice($this->existing(2, '2025-01-01', null, '55', '95'))
            ->newPriceFrom(new DateTimeImmutable('2026-07-01'), '60', '105')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString(
            '<ratechange id="1"><begindate>20240101</begindate><enddate>20241231</enddate>'
            . '<internalrate>50.0000</internalrate><externalrate>90.0000</externalrate></ratechange>'
            . '<ratechange id="2"><begindate>20250101</begindate><enddate>20260630</enddate>'
            . '<internalrate>55.0000</internalrate><externalrate>95.0000</externalrate></ratechange>'
            . '<ratechange><begindate>20260701</begindate><enddate>29991231</enddate>'
            . '<internalrate>60.0000</internalrate><externalrate>105.0000</externalrate></ratechange>',
            $xml,
        );
    }

    public function testNewPriceFromOnTheStartDateOfTheOpenPriceReplacesIt(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->existingPrice($this->existing(2, '2025-01-01', null, '55', '95'))
            ->newPriceFrom(new DateTimeImmutable('2025-01-01'), '60', '105')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString(
            '<ratechanges><ratechange id="2"><begindate>20250101</begindate><enddate>29991231</enddate>'
            . '<internalrate>60.0000</internalrate><externalrate>105.0000</externalrate></ratechange></ratechanges>',
            $xml,
        );
    }

    public function testNewPriceFromWithoutPricesAddsOne(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->newPriceFrom(new DateTimeImmutable('2026-01-01'), '60', '105')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString('<ratechange><begindate>20260101</begindate><enddate>29991231</enddate>', $xml);
    }

    public function testNewPriceFromBeforeTheOpenPriceThrows(): void
    {
        $input = RateInput::time('CONSULT', 'Consultancy')->existingPrice($this->existing(2, '2025-01-01', null, '55', '95'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The current price starts on 2025-01-01, after 2024-07-01');

        $input->newPriceFrom(new DateTimeImmutable('2024-07-01'), '60', '105');
    }

    public function testRemovesASavedPriceWithStatusDeleted(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->existingPrice($this->existing(1, '2024-01-01', '2024-12-31', '50', '90'))
            ->existingPrice($this->existing(2, '2025-01-01', null, '55', '95'))
            ->removePrice(new DateTimeImmutable('2024-01-01'))
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString(
            '<ratechange id="2"><begindate>20250101</begindate>',
            $xml,
        );
        self::assertStringContainsString(
            '<ratechange id="1" status="deleted"><begindate>20240101</begindate><enddate>20241231</enddate>'
            . '<internalrate>50.0000</internalrate><externalrate>90.0000</externalrate></ratechange>'
            . '</ratechanges>',
            $xml,
        );
    }

    public function testRemovingANewPriceLeavesItOut(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->price(new DateTimeImmutable('2024-01-01'), '50', '90', new DateTimeImmutable('2024-12-31'))
            ->price(new DateTimeImmutable('2025-01-01'), '55', '95')
            ->removePrice(new DateTimeImmutable('2024-01-01'))
            ->toXml('1000')
            ->toXml();

        self::assertStringNotContainsString('20240101', $xml);
        self::assertStringNotContainsString('deleted', $xml);
    }

    public function testAddingARemovedPriceAgainKeepsItsId(): void
    {
        $xml = RateInput::time('CONSULT', 'Consultancy')
            ->existingPrice($this->existing(2, '2025-01-01', null, '55', '95'))
            ->removePrice(new DateTimeImmutable('2025-01-01'))
            ->price(new DateTimeImmutable('2025-01-01'), '57', '97')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString('<ratechanges><ratechange id="2"><begindate>20250101</begindate>', $xml);
        self::assertStringNotContainsString('deleted', $xml);
    }

    public function testRemovingAnUnknownPriceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate CONSULT has no price from 2025-01-01.');

        RateInput::time('CONSULT', 'Consultancy')->removePrice(new DateTimeImmutable('2025-01-01'));
    }

    public function testRefusesOverlappingPrices(): void
    {
        $input = RateInput::time('CONSULT', 'Consultancy')->price(new DateTimeImmutable('2025-01-01'), '55', '95');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Rate CONSULT: the price from 2026-01-01 overlaps the price from 2025-01-01 (without end date; use newPriceFrom()).',
        );

        $input->price(new DateTimeImmutable('2026-01-01'), '60', '105');
    }

    public function testAcceptsAdjacentPrices(): void
    {
        $input = RateInput::time('CONSULT', 'Consultancy')
            ->price(new DateTimeImmutable('2025-01-01'), '55', '95', new DateTimeImmutable('2025-12-31'))
            ->price(new DateTimeImmutable('2026-01-01'), '60', '105');

        self::assertSame(2, substr_count($input->toXml('1000')->toXml(), '<ratechange>'));
    }

    public function testRefusesAPriceThatEndsBeforeItStarts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot end on 2024-12-31, before it starts');

        RateInput::time('CONSULT', 'Consultancy')
            ->price(new DateTimeImmutable('2025-01-01'), '55', '95', new DateTimeImmutable('2024-12-31'));
    }

    public function testRequiresAPrice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rate CONSULT needs at least one price: add one with price().');

        RateInput::time('CONSULT', 'Consultancy')->toXml('1000');
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'lowercase code' => [static fn () => RateInput::time('km', 'Kilometres'), '"km" is not a code'];
        yield 'empty name' => [static fn () => RateInput::time('KM', ''), 'Rate name must not be empty.'];
        yield 'long name' => [static fn () => RateInput::time('KM', str_repeat('x', 41)), 'Rate name must be at most 40'];
        yield 'long short name' => [
            static fn () => RateInput::time('KM', 'Kilometres')->shortName(str_repeat('x', 21)),
            'Rate short name must be at most 20',
        ];
        yield 'unit 0' => [static fn () => RateInput::time('KM', 'Kilometres')->unit(0), 'Rate unit must be 1 or more, 0 given.'];
        yield 'invalid currency' => [static fn () => RateInput::time('KM', 'Kilometres')->currency('eur'), '"eur" is not a code'];
        yield 'price with comma' => [
            static fn () => RateInput::time('KM', 'Kilometres')->price(new DateTimeImmutable(), UnitPrice::fromString('0,15'), '0.23'),
            '"0,15" is not a unit price',
        ];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalidInputs')]
    public function testRefusesInvalidInput(callable $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    /**
     * @param numeric-string $internal
     * @param numeric-string $external
     */
    private function existing(int $id, string $from, ?string $until, string $internal, string $external): RatePrice
    {
        return new RatePrice(
            $id,
            RateStatus::Active,
            new DateTimeImmutable($from),
            $until === null ? null : new DateTimeImmutable($until),
            UnitPrice::of($internal),
            UnitPrice::of($external),
        );
    }
}
