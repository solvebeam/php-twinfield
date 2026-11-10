<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A posting rule of a customer or supplier to create, update or delete. Immutable: every
 * method returns a new input.
 *
 *   $rule = PostingRuleInput::create('EUR', '125.00')
 *       ->description('Monthly fee')
 *       ->line(PostingRuleLine::create('8000', '80')->vatCode('VH'))
 *       ->line(PostingRuleLine::create('8010', '20')->vatCode('VH'));
 *
 * For an existing rule, start from `$rule->edit()`, which keeps its id.
 */
final readonly class PostingRuleInput
{
    /**
     * @param list<PostingRuleLine> $lines
     */
    private function __construct(
        /** Id of an existing posting rule; null for a new one. */
        public ?int $id,
        public ?string $currency,
        public ?Amount $amount,
        public ?string $description,
        public array $lines,
        /** True to delete the existing rule with this id. */
        public bool $deleted = false,
    ) {
        if ($currency !== null) {
            XmlValue::code($currency);
        }

        if ($id !== null && $id < 1) {
            throw new InvalidArgumentException(sprintf('Posting rule id must be 1 or more, %d given.', $id));
        }

        if ($deleted && $id === null) {
            throw new InvalidArgumentException('Only an existing posting rule (with an id) can be deleted.');
        }

        if ($description !== null) {
            XmlValue::maxLength('posting rule description', $description, 40);
        }
    }

    /**
     * @param string $currency Currency code, e.g. `EUR`.
     * @param Amount|numeric-string $amount E.g. `'125.00'`.
     */
    public static function create(string $currency, Amount|string $amount): self
    {
        return new self(null, $currency, Amount::of($amount), null, []);
    }

    /**
     * Input with all values of an existing posting rule, including its id. Use `$rule->edit()`.
     */
    public static function fromPostingRule(PostingRule $rule): self
    {
        return new self($rule->id, $rule->currency, $rule->amount, $rule->description, $rule->lines);
    }

    /**
     * Currency and amount of the rule.
     *
     * @param Amount|numeric-string $amount
     */
    public function amount(string $currency, Amount|string $amount): self
    {
        return new self($this->id, $currency, Amount::of($amount), $this->description, $this->lines, $this->deleted);
    }

    /**
     * This existing posting rule, to delete. `withoutPostingRule()` of the customer and supplier
     * inputs uses it.
     */
    public function deleted(): self
    {
        return new self($this->id, $this->currency, $this->amount, $this->description, $this->lines, true);
    }

    public function id(?int $id): self
    {
        return new self($id, $this->currency, $this->amount, $this->description, $this->lines, $this->deleted);
    }

    /**
     * At most 40 characters; null removes it.
     */
    public function description(?string $description): self
    {
        $description = $description === '' ? null : $description;

        return new self($this->id, $this->currency, $this->amount, $description, $this->lines, $this->deleted);
    }

    public function line(PostingRuleLine $line): self
    {
        return $this->lines(...[...$this->lines, $line]);
    }

    /**
     * Replaces all lines.
     */
    public function lines(PostingRuleLine ...$lines): self
    {
        $lines = array_values($lines);

        return new self($this->id, $this->currency, $this->amount, $this->description, $lines, $this->deleted);
    }

    /**
     * Appends the `<postingrule>` element to `<postingrules>`.
     *
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $rules): void
    {
        $attributes = ['status' => $this->deleted ? 'deleted' : 'active'];

        if ($this->id !== null) {
            $attributes = ['id' => (string) $this->id, ...$attributes];
        }

        $rule = $xml->add($rules, 'postingrule', null, $attributes);

        $xml->add($rule, 'currency', $this->currency);
        $xml->add($rule, 'amount', $this->amount?->value);
        $xml->add($rule, 'description', $this->description);

        $lines = $xml->add($rule, 'lines');

        foreach ($this->lines as $line) {
            $element = $xml->add($lines, 'line');

            $xml->add($element, 'office', $line->company);
            $xml->add($element, 'dimension1', $line->generalLedger);
            $xml->add($element, 'dimension2', $line->costCenter);
            $xml->add($element, 'dimension3', $line->project);
            $xml->add($element, 'ratio', $line->ratio);
            $xml->add($element, 'vatcode', $line->vatCode);
            $xml->add($element, 'description', $line->description);
        }
    }
}
