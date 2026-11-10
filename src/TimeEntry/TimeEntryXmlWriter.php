<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TimeEntry;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Writes time entries as a `<teqs>` request.
 *
 *   echo TimeEntryXmlWriter::request('1000', $input)->toXml();
 *
 * @internal
 */
final class TimeEntryXmlWriter
{
    /**
     * @throws InvalidArgumentException For a time entry without lines.
     */
    public static function request(string $company, TimeEntryInput ...$inputs): XmlBuilder
    {
        if ($inputs === []) {
            throw new InvalidArgumentException('A time entry request needs at least one time entry.');
        }

        $xml = XmlBuilder::create('teqs');

        foreach ($inputs as $input) {
            self::entry($xml, $company, $input);
        }

        return $xml;
    }

    private static function entry(XmlBuilder $xml, string $company, TimeEntryInput $input): void
    {
        if ($input->lines === []) {
            throw new InvalidArgumentException('A time entry needs at least one time or quantity line.');
        }

        $entry = $xml->add($xml->root(), 'teq');

        $header = $xml->add($entry, 'header');
        $xml->add($header, 'office', $company);
        $xml->add($header, 'code', $input->type->value);
        $xml->add($header, 'user', $input->user);
        $xml->add($header, 'date', XmlValue::date($input->date));
        self::optional($xml, $header, 'prj1', $input->project);
        self::optional($xml, $header, 'prj2', $input->activity);

        $lines = $xml->add($entry, 'lines');

        foreach ($input->lines as $line) {
            if ($line instanceof TimeLineInput) {
                $element = $xml->add($lines, 'line', null, ['type' => 'TIME']);
                $xml->add($element, 'duration', (string) $line->duration);
                self::optional($xml, $element, 'description', $line->description);
                self::optional($xml, $element, 'billable', self::flag($line->billable));
                self::optional($xml, $element, 'ratecode', $line->rate);

                continue;
            }

            $element = $xml->add($lines, 'line', null, ['type' => 'QUANTITY']);
            $xml->add($element, 'value1', $line->quantity);
            self::optional($xml, $element, 'ratecode1', $line->rate);
            self::optional($xml, $element, 'billable1', self::flag($line->billable));
        }
    }

    /**
     * Adds the element, or nothing when the value is null: Twinfield then uses its default.
     */
    private static function optional(XmlBuilder $xml, DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $xml->add($parent, $name, $value);
        }
    }

    private static function flag(?bool $value): ?string
    {
        return $value === null ? null : ($value ? '1' : '0');
    }
}
