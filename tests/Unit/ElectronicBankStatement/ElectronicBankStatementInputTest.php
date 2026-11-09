<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ElectronicBankStatement;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementInput;
use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementLineInput;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\Transaction\DebitCredit;

final class ElectronicBankStatementInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $input = ElectronicBankStatementInput::forBankBook('BNK');
        $withLine = $input->receive('1.00');

        self::assertSame([], $input->lines());
        self::assertCount(1, $withLine->lines());
    }

    public function testCalculatesTheTotal(): void
    {
        $input = ElectronicBankStatementInput::forBankBook('BNK')
            ->receive('151.00')
            ->pay('7.50')
            ->line(new ElectronicBankStatementLineInput(DebitCredit::Debit, '0.01'));

        self::assertSame('143.49', $input->total()->value);
    }

    public function testAcceptsBalancesThatAddUp(): void
    {
        $xml = XmlBuilder::create('statements');

        ElectronicBankStatementInput::forBankBook('BNK')
            ->number(2, startValue: '-10.00', closeValue: '133.50')
            ->receive('151.00')
            ->pay('7.50')
            ->appendTo($xml, $xml->root(), '1000');

        self::assertStringContainsString('<startvalue>-10.00</startvalue><closevalue>133.50</closevalue>', $xml->toXml());
    }

    public function testRefusesBalancesThatDoNotAddUp(): void
    {
        $xml = XmlBuilder::create('statements');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('opening balance 7.68 plus 143.50 is 151.18, but the closing balance is 151.00');

        ElectronicBankStatementInput::forBankBook('BNK')
            ->number(2, startValue: '7.68', closeValue: '151.00')
            ->receive('151.00')
            ->pay('7.50')
            ->appendTo($xml, $xml->root(), '1000');
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty IBAN' => [static fn () => ElectronicBankStatementInput::forIban(' '), 'IBAN must not be empty'];
        yield 'empty account' => [static fn () => ElectronicBankStatementInput::forAccount(''), 'account number must not be empty'];
        yield 'lowercase bank book' => [static fn () => ElectronicBankStatementInput::forBankBook('bnk'), 'is not a code'];
        yield 'lowercase currency' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->currency('eur'), 'is not a code'];
        yield 'number zero' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->number(0), 'must be 1 or more'];
        yield 'empty sort code' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->sortCode(''), 'sort code must not be empty'];
        yield 'zero value' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->receive('0.00'), 'must be more than zero'];
        yield 'negative value' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('-1.00'), 'must be more than zero'];
        yield 'three decimals' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('1.005'), 'is not an amount'];
        yield 'type too long' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('1.00', type: 'N1000'), 'at most 4 characters'];
        yield 'reference too long' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('1.00', reference: str_repeat('x', 41)), 'at most 40 characters'];
        yield 'description too long' => [static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('1.00', str_repeat('x', 501)), 'at most 500 characters'];
        yield 'both contra fields' => [
            static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('1.00', contraIban: 'NL20INGB0001234567', contraAccount: '1234567'),
            'not both',
        ];
        yield 'contra name without account' => [
            static fn () => ElectronicBankStatementInput::forBankBook('BNK')->pay('1.00', contraIban: 'NL20INGB0001234567', contraName: 'Supplier'),
            'needs a contra account number',
        ];
    }

    /**
     * @param Closure(): mixed $create
     */
    #[DataProvider('invalidInputs')]
    public function testRefusesInvalidInput(Closure $create, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $create();
    }
}
