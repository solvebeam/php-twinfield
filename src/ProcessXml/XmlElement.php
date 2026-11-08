<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use BackedEnum;
use DateTimeImmutable;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Strict reader for an element of a ProcessXml response.
 *
 *   $dimension = XmlElement::root($result);
 *   $name = $dimension->string('name');
 *   $from = $dimension->child('projects')->nullableDate('validfrom');
 *
 * Every reader throws an UnexpectedResponseException when an element or attribute is
 * missing or has an invalid value, with the path in the message, e.g.
 * `dimension/projects/quantities/quantity[2]/label is missing.`
 */
final readonly class XmlElement
{
    public function __construct(
        public DOMElement $element,
        public string $path,
    ) {
    }

    public static function root(ProcessXmlResult $result): self
    {
        return new self($result->root(), (string) $result->root()->localName);
    }

    public function child(string $name): self
    {
        return $this->nullableChild($name)
            ?? throw new UnexpectedResponseException(sprintf('%s/%s is missing.', $this->path, $name));
    }

    /**
     * Only use this for elements Twinfield leaves out in valid responses.
     */
    public function nullableChild(string $name): ?self
    {
        foreach ($this->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return new self($child, $this->path . '/' . $name);
            }
        }

        return null;
    }

    public function text(): string
    {
        return $this->element->textContent;
    }

    /**
     * Text of a child element that must not be empty.
     */
    public function string(string $name): string
    {
        $child = $this->child($name);

        if ($child->text() === '') {
            throw new UnexpectedResponseException(sprintf('%s is empty.', $child->path));
        }

        return $child->text();
    }

    /**
     * Text of a child element that is there but may be empty (null). Only use this
     * where an empty value is valid Twinfield behaviour; a missing element still throws.
     */
    public function nullableString(string $name): ?string
    {
        $text = $this->child($name)->text();

        return $text === '' ? null : $text;
    }

    public function bool(string $name): bool
    {
        return $this->child($name)->asBool();
    }

    /**
     * `true`, `false`, or empty (null) where Twinfield allows an empty value.
     */
    public function nullableBool(string $name): ?bool
    {
        $child = $this->child($name);

        return $child->text() === '' ? null : $child->asBool();
    }

    public function int(string $name): int
    {
        $child = $this->child($name);

        if (preg_match('~^-?\d+$~', $child->text()) !== 1) {
            throw $child->invalid('an integer');
        }

        return (int) $child->text();
    }

    /**
     * A date `yyyymmdd`, or an empty element (null).
     */
    public function nullableDate(string $name): ?DateTimeImmutable
    {
        $child = $this->child($name);

        if ($child->text() === '') {
            return null;
        }

        try {
            return XmlValue::toDate($child->text());
        } catch (InvalidArgumentException) {
            throw $child->invalid('a date yyyymmdd');
        }
    }

    /**
     * A date and time `yyyymmddhhmmss`.
     */
    public function dateTime(string $name): DateTimeImmutable
    {
        $child = $this->child($name);
        $date = preg_match('~^\d{14}$~', $child->text()) === 1
            ? DateTimeImmutable::createFromFormat('!YmdHis', $child->text())
            : false;

        if ($date === false || $date->format('YmdHis') !== $child->text()) {
            throw $child->invalid('a date and time yyyymmddhhmmss');
        }

        return $date;
    }

    /**
     * A backed enum from the text of a child element.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function enum(string $name, string $enum): BackedEnum
    {
        $child = $this->child($name);

        return $enum::tryFrom($child->text()) ?? throw $child->invalid('one of ' . self::enumValues($enum));
    }

    /**
     * A backed enum from the text of a child element that is there but may be empty (null).
     * Only use this where an empty value is valid Twinfield behaviour.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public function nullableEnum(string $name, string $enum): ?BackedEnum
    {
        return $this->child($name)->text() === '' ? null : $this->enum($name, $enum);
    }

    /**
     * A backed enum from an attribute of this element.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function enumAttribute(string $name, string $enum): BackedEnum
    {
        $value = $this->attribute($name);

        return $enum::tryFrom($value) ?? throw new UnexpectedResponseException(sprintf(
            '%s/@%s is "%s", expected one of %s.',
            $this->path,
            $name,
            $value,
            self::enumValues($enum),
        ));
    }

    public function hasAttribute(string $name): bool
    {
        return $this->element->hasAttribute($name);
    }

    public function attribute(string $name): string
    {
        if (!$this->element->hasAttribute($name)) {
            throw new UnexpectedResponseException(sprintf('%s/@%s is missing.', $this->path, $name));
        }

        return $this->element->getAttribute($name);
    }

    public function intAttribute(string $name): int
    {
        $value = $this->attribute($name);

        if (preg_match('~^-?\d+$~', $value) !== 1) {
            throw new UnexpectedResponseException(sprintf(
                '%s/@%s is "%s", expected an integer.',
                $this->path,
                $name,
                $value,
            ));
        }

        return (int) $value;
    }

    public function boolAttribute(string $name): bool
    {
        return match ($this->attribute($name)) {
            'true' => true,
            'false' => false,
            default => throw new UnexpectedResponseException(sprintf(
                '%s/@%s is "%s", expected a boolean (true or false).',
                $this->path,
                $name,
                $this->attribute($name),
            )),
        };
    }

    /**
     * Child elements of a list element, e.g. `child('quantities')->list('quantity')`.
     * Throws on any other child element.
     *
     * @return list<self>
     */
    public function list(string $itemName): array
    {
        $items = [];

        foreach ($this->element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if ($child->localName !== $itemName) {
                throw new UnexpectedResponseException(sprintf(
                    '%s contains <%s>, expected <%s>.',
                    $this->path,
                    $child->localName,
                    $itemName,
                ));
            }

            $items[] = $child;
        }

        return array_map(
            fn (DOMElement $item, int $index): self => new self(
                $item,
                count($items) > 1
                    ? sprintf('%s/%s[%d]', $this->path, $itemName, $index + 1)
                    : $this->path . '/' . $itemName,
            ),
            $items,
            array_keys($items),
        );
    }

    public function invalid(string $expected): UnexpectedResponseException
    {
        return new UnexpectedResponseException(
            sprintf('%s is "%s", expected %s.', $this->path, $this->text(), $expected),
        );
    }

    private function asBool(): bool
    {
        return match ($this->text()) {
            'true' => true,
            'false' => false,
            default => throw $this->invalid('a boolean (true or false)'),
        };
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    private static function enumValues(string $enum): string
    {
        return implode(', ', array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases()));
    }
}
