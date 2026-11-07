<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A posting rule of a customer or supplier, as Twinfield returns it: how an amount is split
 * over general ledger accounts, e.g. for a recurring invoice.
 *
 * To change it, call edit() and pass the result to `CustomerInput::postingRule()`.
 */
final readonly class PostingRule
{
    /**
     * @param list<PostingRuleLine> $lines
     */
    public function __construct(
        public int $id,
        /** Currency code, e.g. `EUR`; null for a rule without amount. */
        public ?string $currency,
        /** Null for a rule that only splits, without fixed amount. */
        public ?Amount $amount,
        /** At most 40 characters; null when not set. */
        public ?string $description,
        public array $lines,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('Posting rule id must be 1 or more, %d given.', $id));
        }
    }

    /**
     * From a `<postingrule>` element of a dimension.
     */
    public static function fromXml(XmlElement $rule): self
    {
        $amount = $rule->child('amount');

        try {
            // Optional in Twinfield: a rule can have an empty <currency/> and <amount/>.
            $value = $amount->text() === '' ? null : Amount::fromString($amount->text());
        } catch (InvalidArgumentException) {
            throw $amount->invalid('an amount');
        }

        return new self(
            id: $rule->intAttribute('id'),
            currency: $rule->nullableString('currency'),
            amount: $value,
            description: $rule->nullableString('description'),
            lines: array_map(PostingRuleLine::fromXml(...), $rule->child('lines')->list('line')),
        );
    }

    /**
     * Input with all values of this posting rule, including its id.
     */
    public function edit(): PostingRuleInput
    {
        return PostingRuleInput::fromPostingRule($this);
    }
}
