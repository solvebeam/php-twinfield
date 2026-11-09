<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\TransactionBlockedValue;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\TransactionBlockedValue\TransactionBlockedValueServiceFaultCode;
use SolveBeam\Twinfield\Twinfield;

final class TransactionBlockedValueResourceTest extends TestCase
{
    public function testRegistersABlockedValueWithFieldsInWsdlOrder(): void
    {
        $http = ReplayClient::fixture('transaction-blocked-value-process');

        $this->twinfield($http)->transactionBlockedValues()->register('MEMO', 202100001, 1, '2000');

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/TransactionBlockedValueService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/TransactionBlockedValueService/Process"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            'i:type="a:RegisterBlockedAmountForTransaction">'
            . '<a:BlockedValue>2000.00</a:BlockedValue>'
            . '<a:CompanyCode>1000</a:CompanyCode>'
            . '<a:TransactionCode>MEMO</a:TransactionCode>'
            . '<a:TransactionLineId>1</a:TransactionLineId>'
            . '<a:TransactionNumber>202100001</a:TransactionNumber>'
            . '</Command>',
            $this->body($http),
        );
        self::assertStringContainsString(
            'xmlns:a="http://schemas.datacontract.org/2004/07/Twinfield.WebServices.TransactionBlockedValueService"',
            $this->body($http),
        );
    }

    public function testAcceptsAnAmount(): void
    {
        $http = ReplayClient::fixture('transaction-blocked-value-process');

        $this->twinfield($http)->transactionBlockedValues()->register('INK', 202600021, 1, Amount::of('12.5'));

        self::assertStringContainsString('<a:BlockedValue>12.50</a:BlockedValue>', $this->body($http));
    }

    public function testUnregistersABlockedValueWithFieldsInWsdlOrder(): void
    {
        $http = ReplayClient::fixture('transaction-blocked-value-process');

        $this->twinfield($http)->transactionBlockedValues()->unregister('MEMO', 202100001, 1);

        self::assertSame(
            '"http://www.twinfield.com/TransactionBlockedValueService/Process"',
            $http->lastRequest()->getHeaderLine('SOAPAction'),
        );
        self::assertStringContainsString(
            'i:type="a:UnregisterBlockedAmountForTransaction">'
            . '<a:CompanyCode>1000</a:CompanyCode>'
            . '<a:TransactionCode>MEMO</a:TransactionCode>'
            . '<a:TransactionLineId>1</a:TransactionLineId>'
            . '<a:TransactionNumber>202100001</a:TransactionNumber>'
            . '</Command>',
            $this->body($http),
        );
    }

    public function testThrowsServiceFaultWithCode(): void
    {
        $http = ReplayClient::fixture('transaction-blocked-value-fault-cannot-be-registered', 500);

        try {
            $this->twinfield($http)->transactionBlockedValues()->register('MEMO', 202100001, 1, '2000.00');
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('TransactionBlockedValueServiceFault', $exception->type);
            self::assertSame(
                TransactionBlockedValueServiceFaultCode::BlockedValueCannotBeRegistered,
                TransactionBlockedValueServiceFaultCode::tryFrom($exception->errorCode),
            );
            self::assertSame('Geblokkeerd bedrag kan niet worden geregistreerd.', $exception->errorMessage);
        }
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'empty day book' => ['', 202100001, 1, '1.00'];
        yield 'zero number' => ['MEMO', 0, 1, '1.00'];
        yield 'negative number' => ['MEMO', -1, 1, '1.00'];
        yield 'zero line' => ['MEMO', 202100001, 0, '1.00'];
        yield 'invalid amount' => ['MEMO', 202100001, 1, '1,50'];
        yield 'three decimals' => ['MEMO', 202100001, 1, '1.005'];
    }

    #[DataProvider('invalidInput')]
    public function testRefusesInvalidInputBeforeSending(string $dayBook, int $number, int $line, string $value): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->transactionBlockedValues()->register($dayBook, $number, $line, $value);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $http->requests);
        }
    }

    public function testUnregisterRefusesInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->transactionBlockedValues()->unregister('MEMO', 202100001, 0);
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->transactionBlockedValues()->unregister('MEMO', 202100001, 1);
    }

    private function body(ReplayClient $http): string
    {
        return (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
