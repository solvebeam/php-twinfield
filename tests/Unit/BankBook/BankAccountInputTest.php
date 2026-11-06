<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BankBook;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\BankBook\BankAccountInput;
use SolveBeam\Twinfield\BankBook\BankBookInput;
use SolveBeam\Twinfield\CommandQuery\Nil;

final class BankAccountInputTest extends TestCase
{
    public function testNormalisesIbanBicAndCountry(): void
    {
        $account = BankAccountInput::create()->iban('nl91 abna 0417 1643 00')->bic('abnanl2a')->country('nl');

        self::assertSame('NL91ABNA0417164300', $account->iban);
        self::assertSame('ABNANL2A', $account->bic);
        self::assertSame('NL', $account->country);
    }

    public function testSendsEmptyRequiredFieldsAsNilAndLeavesOutCountry(): void
    {
        self::assertSame([
            'Bic' => Nil::Value,
            'Country' => null,
            'Iban' => Nil::Value,
            'Name' => Nil::Value,
            'Nbic' => Nil::Value,
            'Number' => '123',
            'ReferenceNumber' => Nil::Value,
        ], BankAccountInput::create()->number('123')->toFields());
    }

    public function testEmptyStringsRemoveValues(): void
    {
        self::assertTrue(BankAccountInput::create()->name('Example')->name('')->isEmpty());
    }

    /**
     * @return iterable<string, array{callable(BankAccountInput): BankAccountInput, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'IBAN check digits' => [static fn (BankAccountInput $a) => $a->iban('NL45INGB0003255113'), '"NL45INGB0003255113" is not a valid IBAN.'];
        yield 'IBAN format' => [static fn (BankAccountInput $a) => $a->iban('3255112'), '"3255112" is not a valid IBAN.'];
        yield 'BIC' => [static fn (BankAccountInput $a) => $a->bic('INGB'), '"INGB" is not a BIC: 8 or 11 letters and digits.'];
        yield 'country' => [static fn (BankAccountInput $a) => $a->country('NLD'), '"NLD" is not a country code such as NL.'];
    }

    #[DataProvider('invalidValues')]
    public function testRefusesInvalidValues(callable $change, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $change(BankAccountInput::create());
    }

    public function testBankBookInputRefusesInvalidCodeAndEmptyName(): void
    {
        try {
            BankBookInput::create('ing bank', 'ING');
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('is not a code', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bank book name must not be empty.');

        BankBookInput::create('ING', '');
    }
}
