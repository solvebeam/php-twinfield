<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\TimeEntry;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\TimeEntry\FailedTimeEntry;
use SolveBeam\Twinfield\TimeEntry\QuantityLineInput;
use SolveBeam\Twinfield\TimeEntry\SavedTimeEntry;
use SolveBeam\Twinfield\TimeEntry\TimeEntryInput;
use SolveBeam\Twinfield\TimeEntry\TimeEntryType;
use SolveBeam\Twinfield\TimeEntry\TimeEntryXmlWriter;
use SolveBeam\Twinfield\TimeEntry\TimeLineInput;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class TimeEntryResourceTest extends TestCase
{
    public function testSavesATimeEntry(): void
    {
        $http = ReplayClient::fixture('time-entry-save');

        $entry = $this->twinfield($http)->timeEntries()->save(
            TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')
                ->time(240, description: 'Deploy')
                ->quantity('1.5'),
        );

        // The request example of the Twinfield documentation, with our company, user and date.
        self::assertSame(
            '<teqs><teq><header><office>1000</office><code>DIRECT</code><user>JDOE</user><date>20261110</date>'
            . '<prj1>P0099</prj1><prj2>A010</prj2></header><lines>'
            . '<line type="TIME"><duration>240</duration><description>Deploy</description></line>'
            . '<line type="QUANTITY"><value1>1.5</value1></line>'
            . '</lines></teq></teqs>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertTrue($entry->company->equals(TestTokens::company('1000')));
        self::assertSame(TimeEntryType::Direct, $entry->type);
        self::assertSame('JDOE', $entry->user->code);
        self::assertSame('John Doe', $entry->user->name);
        self::assertSame('2026-11-10', $entry->date->format('Y-m-d'));
        self::assertSame('P0099', $entry->project?->code);
        self::assertSame('API documentation', $entry->project->name);
        self::assertSame('A010', $entry->activity?->code);
        self::assertSame('1010', $entry->customer);
        self::assertSame('SUPER', $entry->authoriser?->code);

        self::assertCount(1, $entry->timeLines);
        $time = $entry->timeLines[0];
        self::assertSame(1, $time->id);
        self::assertSame(240, $time->duration);
        self::assertSame('240.00', (string) $time->internalValue);
        self::assertSame('400.00', (string) $time->externalValue);
        self::assertSame('Deploy', $time->description);
        self::assertTrue($time->billable);
        self::assertSame('T001', $time->rate->code);
        self::assertSame('Normal rate', $time->rate->name);
        self::assertSame('15.0000', (string) $time->rate->internalPrice);
        self::assertSame('25.0000', (string) $time->rate->externalPrice);
        self::assertSame('15.00', $time->rate->units);
        self::assertSame('EUR', $time->currency);

        self::assertCount(1, $entry->quantityLines);
        $quantity = $entry->quantityLines[0];
        self::assertSame(2, $quantity->id);
        self::assertSame('1.5', $quantity->quantity);
        self::assertSame('15.00', (string) $quantity->internalValue);
        self::assertSame('30.00', (string) $quantity->externalValue);
        self::assertFalse($quantity->billable);
        self::assertSame('KM', $quantity->rate->code);
        self::assertSame('1', $quantity->rate->units);
        self::assertSame('EUR', $quantity->currency);
    }

    public function testWritesEveryFieldOfALine(): void
    {
        $xml = TimeEntryXmlWriter::request(
            '1000',
            TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')
                ->time(60, description: 'Review', billable: false, rate: 'T001')
                ->quantity('12', rate: 'KM', billable: true),
        )->toXml();

        self::assertStringContainsString(
            '<line type="TIME"><duration>60</duration><description>Review</description><billable>0</billable>'
            . '<ratecode>T001</ratecode></line>',
            $xml,
        );
        self::assertStringContainsString(
            '<line type="QUANTITY"><value1>12</value1><ratecode1>KM</ratecode1><billable1>1</billable1></line>',
            $xml,
        );
    }

    public function testSavesAnEntryWithoutProject(): void
    {
        $http = ReplayClient::fixture('time-entry-save-indirect');

        $entry = $this->twinfield($http)->timeEntries()->save(
            TimeEntryInput::indirect('JDOE', new DateTimeImmutable('2026-11-10'))->time(60),
        );

        self::assertSame(
            '<teqs><teq><header><office>1000</office><code>INDIRECT</code><user>JDOE</user><date>20261110</date>'
            . '</header><lines><line type="TIME"><duration>60</duration></line></lines></teq></teqs>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame(TimeEntryType::Indirect, $entry->type);
        self::assertNull($entry->project);
        self::assertNull($entry->activity);
        self::assertNull($entry->customer);
        self::assertNull($entry->authoriser);
        self::assertNull($entry->timeLines[0]->description);
        self::assertSame([], $entry->quantityLines);
    }

    public function testThrowsWhenTwinfieldRefusesTheEntry(): void
    {
        $http = ReplayClient::fixture('time-entry-save-failed');

        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('De gebruiker NOBODY bestaat niet.');

        $this->twinfield($http)->timeEntries()->save(
            TimeEntryInput::direct('NOBODY', new DateTimeImmutable('2026-11-11'), 'P0099', 'A010')->time(60),
        );
    }

    public function testSaveManyGivesAResultPerEntry(): void
    {
        $http = ReplayClient::fixture('time-entry-save-many');
        $monday = TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')->time(240);
        $tuesday = TimeEntryInput::direct('NOBODY', new DateTimeImmutable('2026-11-11'), 'P0099', 'A010')->time(60);

        $result = $this->twinfield($http)->timeEntries()->saveMany($monday, $tuesday);

        self::assertSame(2, substr_count($this->xmlRequest($http->lastRequest()), '<teq>'));
        self::assertCount(2, $result);
        self::assertFalse($result->allSaved());

        self::assertInstanceOf(SavedTimeEntry::class, $result->results[0]);
        self::assertSame(0, $result->results[0]->index);
        self::assertSame($monday, $result->results[0]->input);
        self::assertSame('JDOE', $result->results[0]->timeEntry->user->code);

        self::assertInstanceOf(FailedTimeEntry::class, $result->results[1]);
        self::assertSame(1, $result->results[1]->index);
        self::assertSame($tuesday, $result->results[1]->input);
        self::assertSame(
            'teqs/teq[2]/header/user: De gebruiker NOBODY bestaat niet.',
            $result->results[1]->message(),
        );
        self::assertCount(1, $result->saved());
        self::assertCount(1, $result->failed());
    }

    public function testThrowsWhenAnotherNumberOfEntriesComesBack(): void
    {
        $http = ReplayClient::fixture('time-entry-save-many');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Sent 1 time entries, got 2 back.');

        $this->twinfield($http)->timeEntries()->saveMany(
            TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')->time(240),
        );
    }

    public function testThrowsOnAnotherRootElement(): void
    {
        $http = ReplayClient::fixture('journal-transaction-post');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected a <teqs> response, got <transaction>.');

        $this->twinfield($http)->timeEntries()->save(
            TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')->time(240),
        );
    }

    public function testThrowsOnAnUnknownLineType(): void
    {
        $http = ReplayClient::fixture('time-entry-save-unknown-line');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('@type is "EXPENSE", expected TIME or QUANTITY.');

        $this->twinfield($http)->timeEntries()->save(
            TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')->time(240),
        );
    }

    public function testRefusesAnEntryWithoutLinesBeforeSending(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->timeEntries()->save(
                TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010'),
            );
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('A time entry needs at least one time or quantity line.', $exception->getMessage());
            self::assertSame([], $http->requests);
        }
    }

    public function testSaveManyNeedsAnEntry(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->timeEntries()->saveMany();
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame([], $http->requests);
        }
    }

    public function testTheInputIsImmutable(): void
    {
        $input = TimeEntryInput::personal('JDOE', new DateTimeImmutable('2026-11-10'));
        $withLine = $input->time(480, description: 'Leave');

        self::assertSame([], $input->lines);
        self::assertCount(1, $withLine->lines);
        self::assertSame(TimeEntryType::Personal, $withLine->type);
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidInputs(): iterable
    {
        $date = new DateTimeImmutable('2026-11-10');

        yield 'empty user' => [static fn () => TimeEntryInput::direct('', $date, 'P0099', 'A010')];
        yield 'lowercase user' => [static fn () => TimeEntryInput::direct('jdoe', $date, 'P0099', 'A010')];
        yield 'empty project' => [static fn () => TimeEntryInput::direct('JDOE', $date, '', 'A010')];
        yield 'empty activity' => [static fn () => TimeEntryInput::direct('JDOE', $date, 'P0099', '')];
        yield 'duration zero' => [static fn () => new TimeLineInput(0)];
        yield 'rate that is not a code' => [static fn () => new TimeLineInput(60, rate: 'T 001')];
        // @phpstan-ignore argument.type
        yield 'quantity with a comma' => [static fn () => new QuantityLineInput('1,5')];
        yield 'negative quantity' => [static fn () => new QuantityLineInput('-1')];
        yield 'quantity that is not a number' => [static fn () => new QuantityLineInput('1e3')];
    }

    /**
     * @param callable(): mixed $create
     */
    #[DataProvider('invalidInputs')]
    public function testRefusesInvalidInput(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
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
