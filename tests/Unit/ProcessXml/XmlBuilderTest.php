<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ProcessXml;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

final class XmlBuilderTest extends TestCase
{
    public function testBuildsEscapedXml(): void
    {
        $xml = XmlBuilder::create('dimension', ['status' => 'deleted']);
        $xml->add($xml->root(), 'name', 'R&D <test> "quoted"');
        $xml->add($xml->root(), 'shortname');
        $xml->add($xml->root(), 'vatcode', '');
        $projects = $xml->add($xml->root(), 'projects');
        $xml->add($projects, 'rate', 'T001', ['locked' => 'true', 'inherit' => 'false']);

        self::assertSame(
            '<dimension status="deleted"><name>R&amp;D &lt;test&gt; "quoted"</name><shortname/><vatcode/>'
            . '<projects><rate locked="true" inherit="false">T001</rate></projects></dimension>',
            $xml->toXml(),
        );
        self::assertSame('dimension', $xml->document()->documentElement?->localName);
    }

    public function testChecksMaximumLength(): void
    {
        self::assertSame('Één', XmlValue::maxLength('name', 'Één', 3), 'Counts characters, not bytes.');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name must be at most 3 characters, 4 given: "Four".');

        XmlValue::maxLength('name', 'Four', 3);
    }
}
