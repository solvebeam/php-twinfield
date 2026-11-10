<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\SpreadTransaction;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\SpreadTransaction\SpreadLine;
use SolveBeam\Twinfield\SpreadTransaction\SpreadSettings;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class SpreadTransactionResourceTest extends TestCase
{
    private const ORIGINAL = '<original><office>1000</office><code>INK</code><number>202600001</number></original>';

    private const SETTINGS = '<settings><dim1>2500</dim1><startperiod>2026/01</startperiod>'
        . '<endperiod>2026/02</endperiod></settings>';

    private const SETTINGS_BY_LINES = '<settings><dim1>2500</dim1><linessettings>'
        . '<linesettings><lineid>2</lineid><startperiod>2026/01</startperiod><endperiod>2026/02</endperiod>'
        . '</linesettings>'
        . '<linesettings><lineid>4</lineid><startperiod>2026/03</startperiod><endperiod>2026/05</endperiod>'
        . '</linesettings>'
        . '</linessettings></settings>';

    public function testReadsASpread(): void
    {
        $http = ReplayClient::fixture('spread-transaction-get');

        $spread = $this->twinfield($http)->spreadTransactions()->get('INK', 202600001);

        self::assertSame(
            '<spread action="getspreadtransactioninfo">' . self::ORIGINAL . '</spread>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('INK', $spread->original->dayBook);
        self::assertSame(202600001, $spread->original->number);
        self::assertTrue($spread->original->company->equals(TestTokens::company('1000')));
        self::assertTrue($spread->isSpread());

        self::assertNotNull($spread->settings);
        self::assertSame('2500', $spread->settings->intermediateAccount);
        self::assertFalse($spread->settings->isByLines());
        self::assertSame('2026/01', (string) $spread->settings->startPeriod);
        self::assertSame('2026/02', (string) $spread->settings->endPeriod);
        self::assertSame([], $spread->settings->lines);

        self::assertCount(3, $spread->spreadTransactions);
        self::assertSame('KOSTENVERDELING', $spread->spreadTransactions[0]->dayBook);
        self::assertSame(
            [202600002, 202600003, 202600004],
            array_map(static fn ($transaction): int => $transaction->number, $spread->spreadTransactions),
        );
        self::assertSame('1000', $spread->spreadTransactions[2]->company->code);
    }

    public function testReadsASpreadByLines(): void
    {
        $http = ReplayClient::fixture('spread-transaction-get-by-lines');

        $settings = $this->twinfield($http)->spreadTransactions()->get('INK', 202600001)->settings;

        self::assertNotNull($settings);
        self::assertTrue($settings->isByLines());
        self::assertNull($settings->startPeriod);
        self::assertNull($settings->endPeriod);
        self::assertCount(2, $settings->lines);
        self::assertSame(2, $settings->lines[0]->lineId);
        self::assertSame('2026/01', (string) $settings->lines[0]->startPeriod);
        self::assertSame('2026/02', (string) $settings->lines[0]->endPeriod);
        self::assertSame(4, $settings->lines[1]->lineId);
        self::assertSame('2026/03', (string) $settings->lines[1]->startPeriod);
        self::assertSame('2026/05', (string) $settings->lines[1]->endPeriod);
    }

    public function testReadsATransactionWithoutSpread(): void
    {
        $http = ReplayClient::fixture('spread-transaction-get-not-spread');

        $spread = $this->twinfield($http)->spreadTransactions()->get('INK', 202600001);

        self::assertNull($spread->settings);
        self::assertSame([], $spread->spreadTransactions);
        self::assertFalse($spread->isSpread());
    }

    public function testThrowsWhenTheTransactionIsNotFound(): void
    {
        $http = ReplayClient::fixture('spread-transaction-get-not-found');

        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Originele transactie INK:202699999 is niet gevonden.');

        $this->twinfield($http)->spreadTransactions()->get('INK', 202699999);
    }

    public function testPostsFinal(): void
    {
        $http = ReplayClient::fixture('spread-transaction-post-final');

        $spread = $this->twinfield($http)->spreadTransactions()->postFinal(
            'INK',
            202600001,
            '2500',
            Period::of(2026, 1),
            Period::of(2026, 2),
        );

        self::assertSame(
            '<spread action="postfinal">' . self::ORIGINAL . self::SETTINGS . '</spread>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertCount(3, $spread->spreadTransactions);
        self::assertSame('2500', $spread->settings?->intermediateAccount);
    }

    public function testPostsProvisional(): void
    {
        $http = ReplayClient::fixture('spread-transaction-post-provisional');

        $spread = $this->twinfield($http)->spreadTransactions()->postProvisional(
            'INK',
            202600001,
            '2500',
            Period::of(2026, 1),
            Period::of(2026, 2),
        );

        self::assertSame(
            '<spread action="postprovisional">' . self::ORIGINAL . self::SETTINGS . '</spread>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertTrue($spread->isSpread());
    }

    public function testPostsFinalByLines(): void
    {
        $http = ReplayClient::fixture('spread-transaction-post-final-by-lines');

        $spread = $this->twinfield($http)->spreadTransactions()->postFinalByLines(
            'INK',
            202600001,
            '2500',
            new SpreadLine(2, Period::of(2026, 1), Period::of(2026, 2)),
            new SpreadLine(4, Period::of(2026, 3), Period::of(2026, 5)),
        );

        self::assertSame(
            '<spread action="postfinalbylines">' . self::ORIGINAL . self::SETTINGS_BY_LINES . '</spread>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertCount(2, $spread->settings->lines ?? []);
        self::assertCount(3, $spread->spreadTransactions);
    }

    public function testPostsProvisionalByLines(): void
    {
        $http = ReplayClient::fixture('spread-transaction-post-provisional-by-lines');

        $this->twinfield($http)->spreadTransactions()->postProvisionalByLines(
            'INK',
            202600001,
            '2500',
            new SpreadLine(2, Period::of(2026, 1), Period::of(2026, 2)),
            new SpreadLine(4, Period::of(2026, 3), Period::of(2026, 5)),
        );

        self::assertSame(
            '<spread action="postprovisionalbylines">' . self::ORIGINAL . self::SETTINGS_BY_LINES . '</spread>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testASpreadByLinesThatRemovesTheSpreadHasNoSpreadTransactions(): void
    {
        // "If all periods of line spread settings are equal to transaction period then undo
        // action will be triggered": the response is that of an undo.
        $http = ReplayClient::fixture('spread-transaction-undo');

        $spread = $this->twinfield($http)->spreadTransactions()->postProvisionalByLines(
            'INK',
            202600001,
            '2500',
            new SpreadLine(2, Period::of(2026, 1), Period::of(2026, 1)),
        );

        self::assertFalse($spread->isSpread());
        self::assertNull($spread->settings);
    }

    public function testUndoes(): void
    {
        $http = ReplayClient::fixture('spread-transaction-undo');

        $this->twinfield($http)->spreadTransactions()->undo('INK', 202600001);

        self::assertSame(
            '<spread action="undo">' . self::ORIGINAL . '</spread>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testThrowsWhenTheResponseIsAboutAnotherTransaction(): void
    {
        $http = ReplayClient::fixture('spread-transaction-get');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Asked for the spread of INK 202600009 in 1000, got INK 202600001 in 1000.');

        $this->twinfield($http)->spreadTransactions()->get('INK', 202600009);
    }

    public function testThrowsOnAnotherRootElement(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected a <spread> response, got <reverse>.');

        $this->twinfield($http)->spreadTransactions()->get('INK', 202600001);
    }

    public function testThrowsOnAPeriodThatIsNotAPeriod(): void
    {
        $http = ReplayClient::fixture('spread-transaction-get-invalid-period');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('endperiod is "February", expected a period yyyy/pp.');

        $this->twinfield($http)->spreadTransactions()->get('INK', 202600001);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'empty day book' => ['', 202600001, '2500'];
        yield 'lowercase day book' => ['ink', 202600001, '2500'];
        yield 'number zero' => ['INK', 0, '2500'];
        yield 'empty intermediate account' => ['INK', 202600001, ''];
        yield 'intermediate account that is not a code' => ['INK', 202600001, '25 00'];
    }

    #[DataProvider('invalidRequests')]
    public function testRefusesAnInvalidRequestBeforeSending(
        string $dayBook,
        int $number,
        string $intermediateAccount,
    ): void {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->spreadTransactions()->postProvisional(
                $dayBook,
                $number,
                $intermediateAccount,
                Period::of(2026, 1),
                Period::of(2026, 2),
            );
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $http->requests);
        }
    }

    public function testRefusesAnEndPeriodBeforeTheStartPeriod(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->spreadTransactions()->postFinal(
                'INK',
                202600001,
                '2500',
                Period::of(2026, 3),
                Period::of(2026, 2),
            );
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('End period 2026/02 is before start period 2026/03.', $exception->getMessage());
            self::assertSame([], $http->requests);
        }
    }

    public function testRefusesASpreadByLinesWithoutLines(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->spreadTransactions()->postFinalByLines('INK', 202600001, '2500');
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Spread settings by lines need at least one line.', $exception->getMessage());
            self::assertSame([], $http->requests);
        }
    }

    public function testRefusesTheSameLineTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Spread settings by lines have the same line id more than once.');

        SpreadSettings::byLines(
            '2500',
            new SpreadLine(2, Period::of(2026, 1), Period::of(2026, 2)),
            new SpreadLine(2, Period::of(2026, 3), Period::of(2026, 4)),
        );
    }

    public function testALineNeedsAnIdFromOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line id must be 1 or more, got 0.');

        new SpreadLine(0, Period::of(2026, 1), Period::of(2026, 2));
    }

    public function testALineNeedsAnEndPeriodFromTheStartPeriod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End period 2025/12 of line 2 is before start period 2026/01.');

        new SpreadLine(2, Period::of(2026, 1), Period::of(2025, 12));
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The client has no company');

        $twinfield->spreadTransactions()->get('INK', 202600001);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        $xmlRequest = $xpath->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
