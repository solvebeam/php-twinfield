<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Dimension;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\AddressType;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

final class AddressInputTest extends TestCase
{
    public function testWritesANewAddress(): void
    {
        $address = AddressInput::postal('Example & Co')
            ->field(1, 'T.a.v. J. Jansen')
            ->field(2, 'Postbus 123')
            ->postcode('1000 AA')
            ->city('Amsterdam')
            ->country('NL')
            ->telephone('020-1234567')
            ->email('post@example.com')
            ->vatNumber('NL001234567B01')
            ->chamberOfCommerceNumber('12345678');

        self::assertSame(
            '<addresses><address type="postal" default="false"><name>Example &amp; Co</name><country>NL</country>'
            . '<city>Amsterdam</city><postcode>1000 AA</postcode><telephone>020-1234567</telephone><telefax/>'
            . '<email>post@example.com</email><field1>T.a.v. J. Jansen</field1><field2>Postbus 123</field2><field3/>'
            . '<field4>NL001234567B01</field4><field5>12345678</field5><field6/></address></addresses>',
            $this->xml($address),
        );
    }

    public function testWritesTheIdOfAnExistingAddress(): void
    {
        self::assertStringStartsWith(
            '<addresses><address id="2" type="contact" default="true">',
            $this->xml(AddressInput::create(AddressType::Contact)->id(2)->default()),
        );
    }

    public function testEmptyStringsAreNull(): void
    {
        $address = AddressInput::invoice('Example')->city('')->name('');

        self::assertNull($address->city);
        self::assertNull($address->name);
    }

    public function testRefusesAnInvalidCountry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"nl" is not a country code');

        AddressInput::invoice()->country('nl');
    }

    public function testRefusesAnUnknownField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address field must be 1–6, 7 given.');

        AddressInput::invoice()->field(7, 'x');
    }

    public function testRefusesALongCity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address city must be at most 40 characters');

        AddressInput::invoice()->city(str_repeat('a', 41));
    }

    private function xml(AddressInput $address): string
    {
        $xml = XmlBuilder::create('addresses');
        $address->appendTo($xml, $xml->root());

        return $xml->toXml();
    }
}
