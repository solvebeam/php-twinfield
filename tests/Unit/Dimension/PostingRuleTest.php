<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Dimension;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

final class PostingRuleTest extends TestCase
{
    public function testWritesAPostingRule(): void
    {
        $rule = PostingRuleInput::create('EUR', '125')
            ->description('Monthly fee')
            ->line(PostingRuleLine::create('8000', '80')->costCenter('100')->project('P001')->vatCode('VH')->description('Fee'))
            ->line(PostingRuleLine::create('8010', '20')->company('1001'));

        $xml = XmlBuilder::create('postingrules');
        $rule->appendTo($xml, $xml->root());

        self::assertSame(
            '<postingrules><postingrule status="active"><currency>EUR</currency><amount>125.00</amount>'
            . '<description>Monthly fee</description><lines>'
            . '<line><office/><dimension1>8000</dimension1><dimension2>100</dimension2><dimension3>P001</dimension3>'
            . '<ratio>80</ratio><vatcode>VH</vatcode><description>Fee</description></line>'
            . '<line><office>1001</office><dimension1>8010</dimension1><dimension2/><dimension3/>'
            . '<ratio>20</ratio><vatcode/><description/></line>'
            . '</lines></postingrule></postingrules>',
            $xml->toXml(),
        );
    }

    public function testOnlyAnExistingRuleCanBeDeleted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only an existing posting rule (with an id) can be deleted.');

        PostingRuleInput::create('EUR', '1')->deleted();
    }

    public function testRefusesAnInvalidRatio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"78,4" is not a ratio');

        PostingRuleLine::create('8000', '78,4');
    }

    public function testPaymentCondition(): void
    {
        $condition = PaymentCondition::of(8, '2.5');

        self::assertSame(8, $condition->discountDays);
        self::assertSame('2.5', $condition->discountPercentage);
    }

    public function testRefusesNegativeDiscountDays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount days must be 0 or more, -1 given.');

        PaymentCondition::of(-1, '2');
    }
}
