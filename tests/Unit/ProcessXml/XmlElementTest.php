<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ProcessXml;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class XmlElementTest extends TestCase
{
    private const string XML = <<<'XML'
        <dimension status="active" result="1">
            <code>A001</code>
            <shortname/>
            <inuse>true</inuse>
            <touched>3</touched>
            <modified>20250314093015</modified>
            <projects>
                <validfrom>20250101</validfrom>
                <validtill/>
                <billable locked="false" forratio="maybe">yes</billable>
                <quantities>
                    <quantity><label>KM</label></quantity>
                    <quantity><label/></quantity>
                </quantities>
            </projects>
        </dimension>
        XML;

    public function testReadsValues(): void
    {
        $dimension = $this->dimension();
        $projects = $dimension->child('projects');

        self::assertSame('dimension', $dimension->path);
        self::assertSame('A001', $dimension->string('code'));
        self::assertNull($dimension->nullableString('shortname'));
        self::assertTrue($dimension->bool('inuse'));
        self::assertSame(3, $dimension->int('touched'));
        self::assertEquals(new DateTimeImmutable('2025-03-14 09:30:15'), $dimension->dateTime('modified'));
        self::assertEquals(new DateTimeImmutable('2025-01-01'), $projects->nullableDate('validfrom'));
        self::assertNull($projects->nullableDate('validtill'));
        self::assertSame(DimensionStatus::Active, $dimension->enumAttribute('status', DimensionStatus::class));
        self::assertFalse($projects->child('billable')->boolAttribute('locked'));
        self::assertNull($dimension->nullableChild('uid'));
    }

    public function testListsItemsWithIndexedPaths(): void
    {
        $quantities = $this->dimension()->child('projects')->child('quantities')->list('quantity');

        self::assertCount(2, $quantities);
        self::assertSame('dimension/projects/quantities/quantity[1]', $quantities[0]->path);
        self::assertSame('KM', $quantities[0]->string('label'));
    }

    /**
     * @return iterable<string, array{callable(XmlElement): mixed, string}>
     */
    public static function invalidReads(): iterable
    {
        yield 'missing element' => [static fn (XmlElement $d): mixed => $d->string('uid'), 'dimension/uid is missing.'];
        yield 'empty required string' => [
            static fn (XmlElement $d): mixed => $d->string('shortname'),
            'dimension/shortname is empty.',
        ];
        yield 'missing nullable element' => [
            static fn (XmlElement $d): mixed => $d->nullableString('uid'),
            'dimension/uid is missing.',
        ];
        yield 'invalid integer' => [
            static fn (XmlElement $d): mixed => $d->int('code'),
            'dimension/code is "A001", expected an integer.',
        ];
        yield 'invalid boolean' => [
            static fn (XmlElement $d): mixed => $d->child('projects')->bool('billable'),
            'dimension/projects/billable is "yes", expected a boolean (true or false).',
        ];
        yield 'invalid date' => [
            static fn (XmlElement $d): mixed => $d->nullableDate('touched'),
            'dimension/touched is "3", expected a date yyyymmdd.',
        ];
        yield 'invalid date and time' => [
            static fn (XmlElement $d): mixed => $d->dateTime('code'),
            'dimension/code is "A001", expected a date and time yyyymmddhhmmss.',
        ];
        yield 'missing attribute' => [
            static fn (XmlElement $d): mixed => $d->child('projects')->child('billable')->attribute('inherit'),
            'dimension/projects/billable/@inherit is missing.',
        ];
        yield 'invalid boolean attribute' => [
            static fn (XmlElement $d): mixed => $d->child('projects')->child('billable')->boolAttribute('forratio'),
            'dimension/projects/billable/@forratio is "maybe", expected a boolean (true or false).',
        ];
        yield 'unknown enum attribute' => [
            static fn (XmlElement $d): mixed => $d->enumAttribute('result', DimensionStatus::class),
            'dimension/@result is "1", expected one of active, deleted, hide.',
        ];
        yield 'empty item in list' => [
            static fn (XmlElement $d): mixed => $d->child('projects')->child('quantities')->list('quantity')[1]->string(
                'label',
            ),
            'dimension/projects/quantities/quantity[2]/label is empty.',
        ];
        yield 'non-integer attribute' => [
            static fn (XmlElement $d): mixed => $d->intAttribute('status'),
            'dimension/@status is "active", expected an integer.',
        ];
        yield 'unknown nullable enum' => [
            static fn (XmlElement $d): mixed => $d->nullableEnum('code', DimensionStatus::class),
            'dimension/code is "A001", expected one of active, deleted, hide.',
        ];
        yield 'other element in list' => [
            static fn (XmlElement $d): mixed => $d->child('projects')->list('validfrom'),
            'dimension/projects contains <validtill>, expected <validfrom>.',
        ];
    }

    public function testReadsAnIntegerAttribute(): void
    {
        self::assertSame(1, $this->dimension()->intAttribute('result'));
    }

    public function testReadsAnEmptyNullableEnumAsNull(): void
    {
        self::assertNull($this->dimension()->nullableEnum('shortname', DimensionStatus::class));
    }

    /**
     * @param callable(XmlElement): mixed $read
     */
    #[DataProvider('invalidReads')]
    public function testThrowsWithThePath(callable $read, string $message): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        $read($this->dimension());
    }

    private function dimension(): XmlElement
    {
        return XmlElement::root(ProcessXmlResult::fromXml((string) preg_replace('~>\s+<~', '><', self::XML)));
    }
}
