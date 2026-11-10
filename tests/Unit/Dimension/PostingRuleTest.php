<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Dimension;

use DOMDocument;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRule;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\PostingRuleLine;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class PostingRuleTest extends TestCase
{
    public function testWritesAPostingRule(): void
    {
        $rule = PostingRuleInput::create('EUR', '125')
            ->description('Monthly fee')
            ->line(
                PostingRuleLine::create('8000', '80')->costCenter('100')->project('P001')->vatCode('VH')->description(
                    'Fee',
                ),
            )
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

        // @phpstan-ignore argument.type (deliberately invalid input, for callers without static analysis)
        PostingRuleLine::create('8000', '78,4');
    }

    public function testReadsLinesWithoutRatioAndLeavesOutEmptyLines(): void
    {
        // As in live posting rules of suppliers: no ratio, the VAT code # (no VAT) and an empty line.
        $rule = PostingRule::fromXml($this->element(
            '<postingrule id="1" status="active"><currency name="Euro" shortname="Euro">EUR</currency><amount/>'
            . '<description>Invoice</description><lines>'
            . '<line><office name="" shortname=""/><dimension1 name="Purchases" dimensiontype="PNL">7000</dimension1>'
            . '<dimension2 dimensiontype="KPL">00012</dimension2><ratio/><vatcode name="No VAT">#</vatcode>'
            . '<description>Purchases</description></line>'
            . '<line><office name="" shortname=""/><dimension1 name="" shortname="" dimensiontype=""/><ratio/>'
            . '<vatcode/><description/></line>'
            . '</lines></postingrule>',
        ));

        self::assertNull($rule->amount);
        self::assertCount(1, $rule->lines);
        self::assertSame('7000', $rule->lines[0]->generalLedger);
        self::assertSame('00012', $rule->lines[0]->costCenter);
        self::assertNull($rule->lines[0]->ratio);
        self::assertSame('#', $rule->lines[0]->vatCode);

        $xml = XmlBuilder::create('postingrules');
        $rule->edit()->appendTo($xml, $xml->root());

        self::assertStringContainsString('<ratio/><vatcode>#</vatcode>', $xml->toXml());
    }

    public function testRefusesAnInvalidRatioInAResponse(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('postingrule/lines/line/ratio is "78,4", expected a ratio.');

        PostingRule::fromXml($this->element(
            '<postingrule id="1" status="active"><currency/><amount/><description/><lines>'
            . '<line><office/><dimension1>7000</dimension1><ratio>78,4</ratio><vatcode/><description/></line>'
            . '</lines></postingrule>',
        ));
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

    private function element(string $xml): XmlElement
    {
        $document = new DOMDocument();
        $document->loadXML($xml);

        return new XmlElement($document->documentElement ?? throw new LogicException('No root.'), 'postingrule');
    }
}
