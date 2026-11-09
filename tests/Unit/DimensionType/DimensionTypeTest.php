<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionType;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionType\AddressLabels;
use SolveBeam\Twinfield\DimensionType\CodeMask;
use SolveBeam\Twinfield\DimensionType\DimensionType;
use SolveBeam\Twinfield\DimensionType\DimensionTypeLevels;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class DimensionTypeTest extends TestCase
{
    public function testReadsTheXml(): void
    {
        $type = DimensionType::fromXml(self::xml(
            '<dimensiontype status="active" result="1"><office>1000</office><code>PRJ</code><name>Projecten</name>'
            . '<shortname>Projecten</shortname><mask>P[0-9][0-9][0-9][0-9]</mask><touched>35</touched>'
            . '<levels><financials>3</financials><time>1</time><fixedassets>0</fixedassets><invoices>0</invoices></levels>'
            . '<address><label1/><label2/><label3/><label4/><label5/><label6/></address></dimensiontype>',
        ), TestTokens::organisation());

        self::assertSame('PRJ', $type->code);
        self::assertSame(35, $type->touched);
        self::assertEquals(new DimensionTypeLevels(3, 1, 0, 0), $type->levels);
        self::assertEquals(new AddressLabels(), $type->addressLabels);
        self::assertTrue($type->mask->matches('P1000'));
    }

    public function testThrowsWhenTheMaskIsMissing(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimensiontype/mask is missing.');

        DimensionType::fromXml(self::xml(
            '<dimensiontype result="1"><office>1000</office><code>PRJ</code><name>Projecten</name><shortname/><touched>1</touched></dimensiontype>',
        ), TestTokens::organisation());
    }

    public function testThrowsForAnotherElement(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        DimensionType::fromXml(self::xml('<dimension result="1"><office>1000</office></dimension>'), TestTokens::organisation());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimension type name must not be empty.');

        new DimensionType(TestTokens::company('1000'), 'DEB', '', null, CodeMask::any(), new DimensionTypeLevels(2, 0, 0, 0), new AddressLabels(), 1);
    }

    public function testRejectsANegativeLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DimensionTypeLevels(-1, 0, 0, 0);
    }

    private static function xml(string $xml): XmlElement
    {
        return XmlElement::root(ProcessXmlResult::fromXml($xml));
    }
}
