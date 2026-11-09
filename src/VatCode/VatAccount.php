<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use InvalidArgumentException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * An account of a VAT percentage as Twinfield returns it: where (a share of) the VAT is posted,
 * and in which box of the VAT return it is reported.
 *
 *   foreach ($vatCode->latestPercentage()->accounts as $account) {
 *       echo $account->generalLedgerAccount->code, ' ', $account->group?->code, ' ';
 *       echo $account->percentage, '%', PHP_EOL;
 *   }
 *
 * A reverse charge code (e.g. intra-community acquisitions) has two lines: `-100` on the VAT
 * payable account and `100` on the input VAT account.
 */
final readonly class VatAccount
{
    public function __construct(
        /** Line id, unique within the percentage. Read only: Twinfield numbers the lines. */
        public int $id,
        public VatAccountLineType $lineType,
        /** General ledger account (`dim1`) the VAT is posted on, with its name. */
        public NamedReference $generalLedgerAccount,
        /** Country of the VAT group, e.g. `NL`; null when no VAT group is set. */
        public ?NamedReference $groupCountry,
        /** VAT group: the box of the VAT return, e.g. `NL1A`; null when not set. */
        public ?NamedReference $group,
        /** Share of the VAT posted on this account, e.g. `100`; negative for reverse charge. */
        public Percentage $percentage,
    ) {
        VatAccountInput::validateShare($percentage);

        if (($groupCountry === null) !== ($group === null)) {
            throw new InvalidArgumentException('A VAT group needs both a country and a group code.');
        }
    }

    /**
     * From an `<account>` element of `<percentage><accounts>`.
     */
    public static function fromXml(XmlElement $account): self
    {
        try {
            $percentage = Percentage::fromString($account->string('percentage'));
        } catch (InvalidArgumentException) {
            throw $account->child('percentage')->invalid('a percentage, e.g. 100.0');
        }

        try {
            return new self(
                id: $account->intAttribute('id'),
                lineType: $account->enum('linetype', VatAccountLineType::class),
                generalLedgerAccount: NamedReference::fromXml($account->child('dim1')),
                // Empty (<groupcountry />, <group />) when the line has no VAT group.
                groupCountry: NamedReference::nullable($account->child('groupcountry')),
                group: NamedReference::nullable($account->child('group')),
                percentage: $percentage,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $account->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * Input with the values of this line, to change and pass to a VatPercentageInput.
     */
    public function edit(): VatAccountInput
    {
        $input = VatAccountInput::create($this->lineType, $this->generalLedgerAccount->code, $this->percentage);

        if ($this->groupCountry === null || $this->group === null) {
            return $input;
        }

        return $input->group($this->groupCountry->code, $this->group->code);
    }
}
