<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CommandQuery;

use BackedEnum;
use DateTimeImmutable;
use DOMElement;
use Exception;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Strict reader for the result of a Command & Query web service.
 *
 *   $result = $twinfield->query('PeriodService', new Query('GetYears'), 'GetYearsResult');
 *   $years = $result->ints('Years');
 *
 * Every reader throws an UnexpectedResponseException when the element is
 * missing, nil or has an invalid value, with the path in the message.
 */
final readonly class ResultElement
{
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        public DOMElement $element,
        /** Path for error messages, e.g. `GetBankStatementsResult/BankStatements/BankStatement[1]`. */
        public string $path,
    ) {
    }

    /**
     * The `Result` element of a query response, checked against the expected `i:type`, e.g. `GetPeriodsResult`.
     *
     * The QueryResponse is a WCF message contract, so `<Result>` is directly in the SOAP body.
     */
    public static function fromQueryResponse(SoapResponse $response, string $resultType): self
    {
        $payload = $response->payloadElement();

        if ($payload === null || $payload->localName !== 'Result') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a query <Result>, received %s.',
                $payload === null ? 'an empty SOAP body' : '<' . $payload->localName . '>',
            ));
        }

        if ($payload->getAttributeNS(self::XSI_NS, 'nil') === 'true') {
            throw new UnexpectedResponseException('Query result is nil.');
        }

        $result = new self($payload, 'Result');
        $type = $result->element->getAttributeNS(self::XSI_NS, 'type');
        $localType = str_contains($type, ':') ? substr($type, (int) strrpos($type, ':') + 1) : $type;

        if ($localType !== $resultType) {
            throw new UnexpectedResponseException(sprintf(
                'Expected query result of type "%s", received "%s".',
                $resultType,
                $type,
            ));
        }

        return new self($result->element, $resultType);
    }

    public function child(string $name): self
    {
        foreach ($this->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                if ($child->getAttributeNS(self::XSI_NS, 'nil') === 'true') {
                    throw new UnexpectedResponseException(sprintf('%s/%s is nil.', $this->path, $name));
                }

                return new self($child, $this->path . '/' . $name);
            }
        }

        throw new UnexpectedResponseException(sprintf('%s/%s is missing.', $this->path, $name));
    }

    public function string(string $name): string
    {
        return $this->child($name)->text();
    }

    /**
     * Text that may be empty (`<b:Iban/>`) or nil; both give null. Only use this where
     * an empty value is valid Twinfield behaviour; a missing element still throws.
     */
    public function nullableString(string $name): ?string
    {
        if ($this->isNil($name)) {
            $this->element($name);

            return null;
        }

        $text = $this->string($name);

        return $text === '' ? null : $text;
    }

    /**
     * A value of a backed enum, e.g. `enum('SignUpState', AutomaticBankProcessingSignUpState::class)`.
     *
     * @template E of BackedEnum
     *
     * @param class-string<E> $enum
     *
     * @return E
     */
    public function enum(string $name, string $enum): BackedEnum
    {
        $child = $this->child($name);
        $values = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

        return $enum::tryFrom($child->text()) ?? throw $child->invalid('one of ' . implode(', ', $values));
    }

    /**
     * Like enum(), but nil (`i:nil="true"`) gives null. Only use this where nil is valid
     * Twinfield behaviour; a missing element still throws.
     *
     * @template E of BackedEnum
     *
     * @param class-string<E> $enum
     *
     * @return ?E
     */
    public function nullableEnum(string $name, string $enum): ?BackedEnum
    {
        return $this->isNil($name) ? null : $this->enum($name, $enum);
    }

    public function int(string $name): int
    {
        return $this->child($name)->asInt();
    }

    /**
     * An `xs:decimal` as an exact string, as Twinfield returns it, e.g. `474.3600`, `-70.0100` or `0`.
     *
     * @return numeric-string
     */
    public function decimal(string $name): string
    {
        $child = $this->child($name);
        $text = $child->text();

        if (preg_match('~^-?\d+(\.\d+)?$~', $text) !== 1 || !is_numeric($text)) {
            throw $child->invalid('a decimal');
        }

        return $text;
    }

    /**
     * An `xs:decimal` that holds a whole number, e.g. a transaction number `202600045`.
     * A fraction other than zeros (`202600045.5`) is invalid.
     */
    public function wholeNumber(string $name): int
    {
        $child = $this->child($name);

        if (preg_match('~^(-?\d+)(\.0+)?$~', $child->text(), $matches) !== 1) {
            throw $child->invalid('a whole number');
        }

        $number = filter_var($matches[1], FILTER_VALIDATE_INT);

        return is_int($number) ? $number : throw $child->invalid('a whole number');
    }

    /**
     * Like wholeNumber(), but nil gives null, e.g. the transaction number of a bank statement
     * that is not posted yet. A missing element still throws.
     */
    public function nullableWholeNumber(string $name): ?int
    {
        return $this->isNil($name) ? null : $this->wholeNumber($name);
    }

    /**
     * An `xs:decimal` money amount. Twinfield sends four decimals (`4843.9600`); only zeros
     * are allowed after the second, so the amount fits in Amount without rounding.
     */
    public function amount(string $name): Amount
    {
        $child = $this->child($name);
        $text = $this->decimal($name);

        if (preg_match('~^(-?\d+(?:\.\d{1,2})?)\d*$~', $text, $matches) !== 1) {
            throw $child->invalid('an amount');
        }

        if (rtrim(substr($text, strlen($matches[1])), '0') !== '') {
            throw $child->invalid('an amount with at most 2 decimals');
        }

        return Amount::fromString($matches[1]);
    }

    /**
     * Like amount(), but nil gives null. A missing element still throws.
     */
    public function nullableAmount(string $name): ?Amount
    {
        return $this->isNil($name) ? null : $this->amount($name);
    }

    public function bool(string $name): bool
    {
        $child = $this->child($name);

        return match ($child->text()) {
            'true' => true,
            'false' => false,
            default => throw $child->invalid('a boolean'),
        };
    }

    /**
     * An `xs:dateTime` such as `2021-02-28T00:00:00`.
     */
    public function dateTime(string $name): DateTimeImmutable
    {
        return $this->child($name)->asDateTime();
    }

    /**
     * An `xs:dateTime` that may be nil (`i:nil="true"`). Only use this where nil is
     * valid Twinfield behaviour; a missing element still throws.
     */
    public function nullableDateTime(string $name): ?DateTimeImmutable
    {
        return $this->isNil($name) ? null : $this->dateTime($name);
    }

    private function element(string $name): DOMElement
    {
        foreach ($this->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return $child;
            }
        }

        throw new UnexpectedResponseException(sprintf('%s/%s is missing.', $this->path, $name));
    }

    private function isNil(string $name): bool
    {
        foreach ($this->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return $child->getAttributeNS(self::XSI_NS, 'nil') === 'true';
            }
        }

        return false;
    }

    private function asDateTime(): DateTimeImmutable
    {
        $text = $this->text();

        if (preg_match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}~', $text) !== 1) {
            throw $this->invalid('a date and time');
        }

        try {
            return new DateTimeImmutable($text);
        } catch (Exception) {
            throw $this->invalid('a date and time');
        }
    }

    /**
     * Items of a list element, e.g. `list('Periods', 'Period')`.
     *
     * @return list<self>
     */
    public function list(string $name, string $itemName): array
    {
        $list = $this->child($name);
        $items = [];
        $index = 0;

        foreach ($list->element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $index++;

            if ($child->localName !== $itemName) {
                throw new UnexpectedResponseException(sprintf(
                    '%s contains <%s>, expected <%s>.',
                    $list->path,
                    $child->localName,
                    $itemName,
                ));
            }

            $items[] = new self($child, sprintf('%s/%s[%d]', $list->path, $itemName, $index));
        }

        return $items;
    }

    /**
     * Items of an `ArrayOfint`, e.g. `ints('Years')`.
     *
     * @return list<int>
     */
    public function ints(string $name): array
    {
        return array_map(static fn (self $item): int => $item->asInt(), $this->list($name, 'int'));
    }

    private function text(): string
    {
        return trim($this->element->textContent);
    }

    private function asInt(): int
    {
        $text = $this->text();

        if (preg_match('~^-?\d+$~', $text) !== 1) {
            throw $this->invalid('an integer');
        }

        return (int) $text;
    }

    private function invalid(string $expected): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf(
            '%s is not %s: "%s".',
            $this->path,
            $expected,
            $this->text(),
        ));
    }
}
