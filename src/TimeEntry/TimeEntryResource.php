<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use DOMElement;
use DOMNode;
use InvalidArgumentException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\MessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Rate\UnitPrice;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Time entries: the time and quantities (e.g. kilometres) a user writes on a project and an
 * activity.
 *
 *   $entries = $twinfield->company('1000')->timeEntries();
 *
 *   $saved = $entries->save(
 *       TimeEntryInput::direct('JDOE', new DateTimeImmutable('2026-11-10'), 'P0099', 'A010')
 *           ->time(240, description: 'Deploy')
 *           ->quantity('1.5', rate: 'KM'),
 *   );
 *
 * Twinfield creates the entry when it does not exist yet and updates it otherwise. There is no
 * read or delete in the Twinfield documentation: read time entries with browse data, e.g. browse
 * code `650_1`.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/time/
 */
final readonly class TimeEntryResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Creates or updates one time entry.
     *
     * @throws InvalidArgumentException When the entry has no lines.
     * @throws ProcessXmlException      When Twinfield refuses it.
     */
    public function save(TimeEntryInput $input): TimeEntry
    {
        $company = $this->twinfield->companyReference();
        $request = TimeEntryXmlWriter::request($company->code, $input)->toXml();
        $entries = self::entries($this->twinfield->processXml($request), 1);

        return self::timeEntry($entries[0], $company);
    }

    /**
     * Creates or updates several time entries in one request. Twinfield saves the valid ones, so
     * this does not throw when some fail: check the result.
     *
     * @throws InvalidArgumentException When there are no entries, or one has no lines.
     */
    public function saveMany(TimeEntryInput ...$inputs): SaveManyTimeEntriesResult
    {
        $inputs = array_values($inputs);
        $company = $this->twinfield->companyReference();
        $request = TimeEntryXmlWriter::request($company->code, ...$inputs)->toXml();

        try {
            $result = $this->twinfield->processXml($request);
        } catch (ProcessXmlException $exception) {
            // Some entries may have been saved; the result says which.
            $result = $exception->result;
        }

        $messages = $result->messages();
        $results = [];

        foreach (self::entries($result, count($inputs)) as $index => $entry) {
            $own = array_values(array_filter(
                $messages,
                static fn (ProcessXmlMessage $message): bool => self::contains($entry->element, $message->element),
            ));
            $warnings = self::ofType($own, MessageType::Warning);

            $results[] = match ($entry->attribute('result')) {
                '1' => new SavedTimeEntry($index, $inputs[$index], self::timeEntry($entry, $company), $warnings),
                '0' => new FailedTimeEntry($index, $inputs[$index], self::ofType($own, MessageType::Error), $warnings),
                default => throw new UnexpectedResponseException(sprintf(
                    '%s/@result is "%s", expected 1 or 0.',
                    $entry->path,
                    $entry->attribute('result'),
                )),
            };
        }

        return new SaveManyTimeEntriesResult($results);
    }

    /**
     * @return list<XmlElement>
     */
    private static function entries(ProcessXmlResult $result, int $expected): array
    {
        $root = XmlElement::root($result);

        if ($root->element->localName !== 'teqs') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <teqs> response, got <%s>.',
                $root->element->localName,
            ));
        }

        $entries = $root->list('teq');

        if (count($entries) !== $expected) {
            throw new UnexpectedResponseException(sprintf(
                'Sent %d time entries, got %d back.',
                $expected,
                count($entries),
            ));
        }

        return $entries;
    }

    private static function timeEntry(XmlElement $entry, CompanyReference $client): TimeEntry
    {
        $header = $entry->child('header');
        $office = $header->string('office');
        $date = $header->nullableDate('date') ?? throw new UnexpectedResponseException(
            sprintf('%s/date is empty.', $header->path),
        );

        // A time entry without project or activity has no <prj1>, <prj2> and <accountsreceivable>
        // (the customer of the project): they are optional in the request too.
        $project = $header->nullableChild('prj1');
        $activity = $header->nullableChild('prj2');
        $customer = $header->nullableChild('accountsreceivable')?->text();
        $authoriser = $header->nullableChild('authoriser');

        $timeLines = [];
        $quantityLines = [];

        foreach ($entry->child('lines')->list('line') as $line) {
            match ($line->attribute('type')) {
                'TIME' => $timeLines[] = self::timeLine($line),
                'QUANTITY' => $quantityLines[] = self::quantityLine($line),
                default => throw new UnexpectedResponseException(sprintf(
                    '%s/@type is "%s", expected TIME or QUANTITY.',
                    $line->path,
                    $line->attribute('type'),
                )),
            };
        }

        return new TimeEntry(
            company: $office === $client->code ? $client : new CompanyReference($client->organisation, $office),
            type: $header->enum('code', TimeEntryType::class),
            user: NamedReference::fromXml($header->child('user')),
            date: $date,
            project: $project === null ? null : NamedReference::nullable($project),
            activity: $activity === null ? null : NamedReference::nullable($activity),
            customer: $customer === null || $customer === '' ? null : $customer,
            authoriser: $authoriser === null ? null : NamedReference::nullable($authoriser),
            timeLines: $timeLines,
            quantityLines: $quantityLines,
        );
    }

    private static function timeLine(XmlElement $line): TimeLine
    {
        $duration = $line->child('duration');

        return new TimeLine(
            id: $line->intAttribute('id'),
            duration: $line->int('duration'),
            internalValue: self::amount($duration, 'internal'),
            externalValue: self::amount($duration, 'external'),
            description: $line->nullableChild('description') === null ? null : $line->nullableString('description'),
            billable: self::flag($line->child('billable')),
            rate: self::rate($line->child('ratecode')),
            currency: $line->string('currency'),
        );
    }

    private static function quantityLine(XmlElement $line): QuantityLine
    {
        $quantity = $line->child('value1');

        if (preg_match('~^-?\d+(?:\.\d+)?$~', $quantity->text()) !== 1 || !is_numeric($quantity->text())) {
            throw $quantity->invalid('a number');
        }

        return new QuantityLine(
            id: $line->intAttribute('id'),
            quantity: $quantity->text(),
            internalValue: self::amount($quantity, 'internal'),
            externalValue: self::amount($quantity, 'external'),
            billable: self::flag($line->child('billable1')),
            rate: self::rate($line->child('ratecode1')),
            currency: $line->string('currency1'),
        );
    }

    private static function rate(XmlElement $rate): TimeEntryRate
    {
        if ($rate->text() === '') {
            throw $rate->invalid('a rate code');
        }

        $units = $rate->attribute('units');

        if (preg_match('~^\d+(?:\.\d+)?$~', $units) !== 1 || !is_numeric($units)) {
            throw new UnexpectedResponseException(sprintf(
                '%s/@units is "%s", expected a number.',
                $rate->path,
                $units,
            ));
        }

        try {
            return new TimeEntryRate(
                code: $rate->text(),
                name: $rate->attribute('name'),
                shortName: $rate->attribute('shortname'),
                internalPrice: UnitPrice::fromString($rate->attribute('internal')),
                externalPrice: UnitPrice::fromString($rate->attribute('external')),
                units: $units,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s has a price that is not valid: %s', $rate->path, $exception->getMessage()),
                previous: $exception,
            );
        }
    }

    private static function amount(XmlElement $element, string $attribute): Amount
    {
        try {
            return Amount::fromString($element->attribute($attribute));
        } catch (InvalidArgumentException) {
            throw new UnexpectedResponseException(sprintf(
                '%s/@%s is "%s", expected an amount.',
                $element->path,
                $attribute,
                $element->attribute($attribute),
            ));
        }
    }

    private static function flag(XmlElement $element): bool
    {
        return match ($element->text()) {
            '1' => true,
            '0' => false,
            default => throw $element->invalid('1 or 0'),
        };
    }

    private static function contains(DOMElement $ancestor, DOMNode $node): bool
    {
        for ($current = $node; $current !== null; $current = $current->parentNode) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ProcessXmlMessage> $messages
     *
     * @return list<ProcessXmlMessage>
     */
    private static function ofType(array $messages, MessageType $type): array
    {
        return array_values(array_filter(
            $messages,
            static fn (ProcessXmlMessage $message): bool => $message->type === $type,
        ));
    }
}
