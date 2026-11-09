<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionGroup;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroup;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupDimension;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupStatus;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class DimensionGroupTest extends TestCase
{
    public function testFindsDimensionsByTypeAndCode(): void
    {
        $group = $this->group();

        self::assertTrue($group->has('DEB', '1000'));
        self::assertTrue($group->has('KPL', '100'));
        self::assertFalse($group->has('KPL', '1000'));
        self::assertSame(['1000', '1001', '100'], $group->codes());
        self::assertSame(['1000', '1001'], $group->codes('DEB'));
        self::assertSame([], $group->codes('CRD'));
        self::assertSame(['DEB', 'KPL'], $group->types());
    }

    public function testEditHasAllValuesAndDimensions(): void
    {
        $input = $this->group()->edit();

        self::assertSame('NL', $input->code);
        self::assertSame('Dutch customers', $input->name);
        self::assertSame('NL customers', $input->shortName);
        self::assertSame([
            ['type' => 'DEB', 'code' => '1000'],
            ['type' => 'DEB', 'code' => '1001'],
            ['type' => 'KPL', 'code' => '100'],
        ], $input->dimensions());
    }

    public function testReadsAGroupWithoutDimensionsElement(): void
    {
        // Right after a delete, Twinfield may leave out <dimensions>.
        $group = DimensionGroup::fromXml(XmlElement::root(ProcessXmlResult::fromXml(
            '<dimensiongroup status="deleted" result="1"><office>1000</office><code>NL</code>'
            . '<name>Dutch customers</name><shortname></shortname><touched>2</touched></dimensiongroup>',
        )), TestTokens::organisation());

        self::assertSame(DimensionGroupStatus::Deleted, $group->status);
        self::assertSame([], $group->dimensions);
    }

    public function testReadsADimensionWithoutName(): void
    {
        $group = XmlElement::root(ProcessXmlResult::fromXml(
            '<dimensiongroup result="1"><dimensions>'
            . '<dimension><type>DEB</type><code name="">1000</code></dimension>'
            . '</dimensions></dimensiongroup>',
        ));
        $dimension = DimensionGroupDimension::fromXml($group->child('dimensions')->list('dimension')[0]);

        self::assertEquals(new DimensionGroupDimension('DEB', '1000', null), $dimension);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimension group name must not be empty.');

        new DimensionGroup(TestTokens::company('1000'), 'NL', '', null, DimensionGroupStatus::Active, 0, []);
    }

    public function testRejectsANegativeTouchedCount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DimensionGroup(TestTokens::company('1000'), 'NL', 'Dutch customers', null, DimensionGroupStatus::Active, -1, []);
    }

    private function group(): DimensionGroup
    {
        return new DimensionGroup(
            company: TestTokens::company('1000'),
            code: 'NL',
            name: 'Dutch customers',
            shortName: 'NL customers',
            status: DimensionGroupStatus::Active,
            touched: 3,
            dimensions: [
                new DimensionGroupDimension('DEB', '1000', 'Example customer'),
                new DimensionGroupDimension('DEB', '1001', null),
                new DimensionGroupDimension('KPL', '100', 'Sales'),
            ],
        );
    }
}
