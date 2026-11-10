<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BankTransaction;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\BankBook\AutomaticBankProcessing;
use SolveBeam\Twinfield\BankBook\AutomaticBankProcessingSignUpState;
use SolveBeam\Twinfield\BankBook\BankAccount;
use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DetailLineInput;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\MatchSetInput;
use SolveBeam\Twinfield\Transaction\Performance;
use SolveBeam\Twinfield\Transaction\PerformanceType;
use SolveBeam\Twinfield\Transaction\TransactionStatus;
use SolveBeam\Twinfield\Transaction\VatLineInput;

final class BankTransactionInputTest extends TestCase
{
    public function testWritesTheDocumentedRequest(): void
    {
        // The create example of the Twinfield documentation.
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2021-11-04'))
            ->currency('EUR')
            ->statement(4, startValue: '974.01')
            ->receive('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid');

        self::assertSame(
            '<transaction destiny="temporary">'
            . '<header><office>001</office><code>BNK</code><currency>EUR</currency><date>20211104</date>'
            . '<statementnumber>4</statementnumber><startvalue>974.01</startvalue>'
            . '<closevalue>1409.56</closevalue></header>'
            . '<lines>'
            . '<line type="total" id="1"><dim1>1010</dim1><debitcredit>debit</debitcredit><value>435.55</value></line>'
            . '<line type="detail" id="2"><dim1>1300</dim1><dim2>1000</dim2><debitcredit>credit</debitcredit>'
            . '<value>435.55</value><invoicenumber>11001770</invoicenumber>'
            . '<description>Invoice paid</description></line>'
            . '</lines>'
            . '</transaction>',
            $input->toXml('001')->toXml(),
        );
    }

    public function testReceiveIsCreditAndPayIsDebit(): void
    {
        $xpath = $this->xpath(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                ->receive('1300', '100.00', customer: '1000')
                ->pay('1600', '30.00', supplier: '2000'),
        );

        self::assertSame('credit', $xpath->evaluate('string(//line[@id="2"]/debitcredit)'));
        self::assertSame('1000', $xpath->evaluate('string(//line[@id="2"]/dim2)'));
        self::assertSame('debit', $xpath->evaluate('string(//line[@id="3"]/debitcredit)'));
        self::assertSame('2000', $xpath->evaluate('string(//line[@id="3"]/dim2)'));
    }

    public function testTotalLineIsDebitWhenMoreComesIn(): void
    {
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->statement(245, '1000.00')
            ->receive('1300', '121.00', customer: '1000')
            ->pay('1600', '50.00', supplier: '2000')
            ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10');

        self::assertSame('58.90', $input->balance()->value);
        self::assertSame('1058.90', $input->closeValue()?->value);

        $xpath = $this->xpath($input);

        self::assertSame('debit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('58.90', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('1058.90', $xpath->evaluate('string(//header/closevalue)'));
        self::assertSame('IH', $xpath->evaluate('string(//line[@id="4"]/vatcode)'));
        self::assertSame('2.10', $xpath->evaluate('string(//line[@id="4"]/vatvalue)'));
    }

    public function testTotalLineIsCreditWhenMoreGoesOut(): void
    {
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->statement(246, '20.00')
            ->pay('1600', '50.00', supplier: '2000');

        $xpath = $this->xpath($input);

        self::assertSame('credit', $xpath->evaluate('string(//line[@type="total"]/debitcredit)'));
        self::assertSame('50.00', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('-30.00', $xpath->evaluate('string(//header/closevalue)'));
    }

    public function testVatLinesReplaceTheVatOfDetailLinesInTheTotal(): void
    {
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->line(DetailLineInput::debit('4500', '10.00')->vat('IH', '2.10'))
            ->vatLine(VatLineInput::debit('IH', '2.10')->turnover('10.00')->baseline(2));

        self::assertSame('-12.10', $input->balance()->value);

        $xpath = $this->xpath($input);

        self::assertSame('12.10', $xpath->evaluate('string(//line[@type="total"]/value)'));
        self::assertSame('vat', $xpath->evaluate('string(//line[@id="3"]/@type)'));
        self::assertSame('10.00', $xpath->evaluate('string(//line[@id="3"]/vatturnover)'));
        self::assertSame('2', $xpath->evaluate('string(//line[@id="3"]/baseline)'));
    }

    public function testWritesOptions(): void
    {
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->final()
            ->autoBalanceVat()
            ->raiseWarnings(false)
            ->ignoreDateWarning()
            ->period(Period::of(2026, 10))
            ->description('Statement 245')
            ->freeText(2, 'Imported')
            ->receive('1300', '1.00');

        $xpath = $this->xpath($input);

        self::assertSame('final', $xpath->evaluate('string(/transaction/@destiny)'));
        self::assertSame('true', $xpath->evaluate('string(/transaction/@autobalancevat)'));
        self::assertSame('false', $xpath->evaluate('string(/transaction/@raisewarning)'));
        self::assertSame('false', $xpath->evaluate('string(//header/date/@raisewarning)'));
        self::assertSame('2026/10', $xpath->evaluate('string(//header/period)'));
        self::assertSame('Imported', $xpath->evaluate('string(//header/freetext2)'));
        self::assertSame('Statement 245', $xpath->evaluate('string(//line[@type="total"]/description)'));
        self::assertSame(TransactionStatus::Final, $input->destiny());
        self::assertSame(
            0.0,
            $xpath->evaluate('count(//header/startvalue)'),
            'Without opening balance, no balances are sent.',
        );
    }

    public function testWritesAllDetailLineFields(): void
    {
        $line = DetailLineInput::debit('4500', '100.00')
            ->costCenter('KPL01')
            ->project('P0001')
            ->invoice('INV-1')
            ->description('Consultancy')
            ->comment('Paid in USD')
            ->vat('IHICT', '0.00')
            ->rate('0.9215')
            ->baseValue('92.15')
            ->reportingRate('1.1')
            ->dueDate(new DateTimeImmutable('2026-12-01'))
            ->lineDate(new DateTimeImmutable('2026-11-02'))
            ->destinationCompany('1001')
            ->performance(
                Performance::services(new DateTimeImmutable('2026-10-31'))->country('DE')->vatNumber('DE123456789'),
            )
            ->freeChar('N');

        $xpath = $this->xpath(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))->line($line),
        );
        $values = [];

        foreach ($xpath->query('//line[@id="2"]/*') ?: [] as $element) {
            self::assertInstanceOf(DOMElement::class, $element);

            $values[$element->nodeName] = $element->textContent;
        }

        self::assertSame([
            'dim1' => '4500',
            'dim2' => 'KPL01',
            'dim3' => 'P0001',
            'debitcredit' => 'debit',
            'value' => '100.00',
            'rate' => '0.9215',
            'basevalue' => '92.15',
            'reprate' => '1.1',
            'duedate' => '20261201',
            'currencydate' => '20261102',
            'invoicenumber' => 'INV-1',
            'description' => 'Consultancy',
            'vatcode' => 'IHICT',
            'vatvalue' => '0.00',
            'destoffice' => '1001',
            'performancetype' => 'services',
            'performancecountry' => 'DE',
            'performancevatnumber' => 'DE123456789',
            'performancedate' => '20261031',
            'freechar' => 'N',
            'comment' => 'Paid in USD',
        ], $values);
    }

    public function testWritesMatchSets(): void
    {
        $line = DetailLineInput::credit('1300', '635.55')
            ->customer('1000')
            ->match(
                MatchSetInput::create(new DateTimeImmutable('2026-11-03'))
                    ->line('VRK', 202600012, 1, '435.55')
                    ->line('VRK', 202600013, 1, '200.00'),
            );

        $xpath = $this->xpath(
            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))->line($line),
        );
        $nodes = $xpath->query('//line[@id="2"]');

        self::assertNotFalse($nodes);

        $element = $nodes->item(0);

        self::assertInstanceOf(DOMElement::class, $element);
        self::assertSame(
            '<line type="detail" id="2"><dim1>1300</dim1><dim2>1000</dim2>'
            . '<debitcredit>credit</debitcredit><value>635.55</value>'
            . '<matches><set><matchdate>20261103</matchdate><lines>'
            . '<line><code>VRK</code><number>202600012</number><line>1</line>'
            . '<method>payment</method><matchvalue>435.55</matchvalue></line>'
            . '<line><code>VRK</code><number>202600013</number><line>1</line>'
            . '<method>payment</method><matchvalue>200.00</matchvalue></line>'
            . '</lines><matchvalue>635.55</matchvalue></set></matches>'
            . '</line>',
            $xpath->document->saveXML($element),
        );
    }

    public function testWithMatchesReplacesMatchSets(): void
    {
        $set = MatchSetInput::create(new DateTimeImmutable('2026-11-03'))->line('VRK', 202600012, 1, '10.00');
        $line = DetailLineInput::credit('1300', '10.00')->match($set)->match($set);

        self::assertCount(2, $line->matches());
        self::assertCount(1, $line->withMatches($set)->matches());
        self::assertSame([], $line->withMatches()->matches());
    }

    public function testMatchSetNeedsALine(): void
    {
        $line = DetailLineInput::credit('1300', '10.00')->match(MatchSetInput::create(new DateTimeImmutable()));

        $this->expectException(LogicException::class);

        BankTransactionInput::create('BNK', '1010', new DateTimeImmutable())->line($line)->toXml('1000');
    }

    public function testMatchSetRefusesInvalidLines(): void
    {
        $set = MatchSetInput::create(new DateTimeImmutable());

        $calls = [
            static fn () => $set->line('vrk', 202600012, 1, '10.00'),
            static fn () => $set->line('VRK', 0, 1, '10.00'),
            static fn () => $set->line('VRK', 202600012, 0, '10.00'),
            static fn () => $set->line('VRK', 202600012, 1, '0.00'),
        ];

        foreach ($calls as $index => $call) {
            try {
                $call();
                self::fail(sprintf('Case %d did not throw.', $index));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInputsAreImmutable(): void
    {
        $base = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'));
        $withLine = $base->receive('1300', '1.00');

        self::assertSame([], $base->lines());
        self::assertCount(1, $withLine->lines());
        self::assertNotSame($base, $base->final());
    }

    public function testMapLinesChangesLines(): void
    {
        $input = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->receive('1300', '1.00', description: 'Old')
            ->receive('1300', '2.00', description: 'Keep')
            ->mapLines(static fn (DetailLineInput $line, int $index): DetailLineInput => $index === 0
                ? $line->description('New')
                : $line);

        $xpath = $this->xpath($input);

        self::assertSame('New', $xpath->evaluate('string(//line[@id="2"]/description)'));
        self::assertSame('Keep', $xpath->evaluate('string(//line[@id="3"]/description)'));
    }

    public function testForBankBookUsesItsLedgerAccount(): void
    {
        $input = BankTransactionInput::forBankBook($this->bankBook('1010'), new DateTimeImmutable('2026-11-03'));

        self::assertSame('ING', $input->dayBook());
        self::assertSame('1010', $input->dim1());
    }

    public function testForBankBookNeedsALedgerAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bank book ING has no ledger account');

        BankTransactionInput::forBankBook($this->bankBook(null), new DateTimeImmutable('2026-11-03'));
    }

    public function testNeedsLines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no lines');

        BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))->toXml('1000');
    }

    public function testNeedsTheVatAmountWithAVatCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 1 has VAT code IH without VAT amount');

        BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->pay('4500', '10.00', vatCode: 'IH')
            ->toXml('1000');
    }

    public function testAnEmptyVatCodeIsNoVatCode(): void
    {
        self::assertNull(DetailLineInput::debit('4500', '10.00')->vat('')->vatCode());
        self::assertSame('#', DetailLineInput::debit('4500', '10.00')->vat('#')->vatCode(), 'The VAT code "no VAT".');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A VAT amount needs a VAT code.');

        DetailLineInput::debit('4500', '10.00')->vat('', '2.10');
    }

    public function testRefusesAnInvalidVatCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"I H" is not a code');

        VatLineInput::debit('I H', '2.10');
    }

    public function testRefusesNegativeAmounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('use the other side');

        BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))->receive('1300', '-1.00');
    }

    public function testRefusesTwoRelationsOnOneLine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pass only one of customer, supplier and costCenter, not customer and supplier');

        BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
            ->receive('1300', '1.00', customer: '1000', supplier: '2000');
    }

    public function testRefusesTooLongDescriptions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 40 characters');

        DetailLineInput::credit('1300', '1.00')->description(str_repeat('x', 41));
    }

    public function testRefusesAnInvalidDayBook(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BankTransactionInput::create('bnk', '1010', new DateTimeImmutable('2026-11-03'));
    }

    public function testRefusesAPerformanceDateForGoods(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Performance::create(PerformanceType::Goods, date: new DateTimeImmutable());
    }

    private function bankBook(?string $account): BankBook
    {
        return new BankBook(
            company: TestTokens::company('1000'),
            code: 'ING',
            name: 'ING',
            shortName: null,
            generalLedgerAccount: $account,
            bankAccount: new BankAccount(null, null, null, null, null, null, null),
            automaticBankProcessing: new AutomaticBankProcessing(
                AutomaticBankProcessingSignUpState::Inactive,
                false,
                false,
                false,
            ),
        );
    }

    private function xpath(BankTransactionInput $input): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($input->toXml('1000')->toXml());

        return new DOMXPath($document);
    }
}
