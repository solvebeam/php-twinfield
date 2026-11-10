<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A line of a posting rule: the share (ratio) of the amount that goes to a general ledger
 * account, optionally with a cost center, project and VAT code.
 *
 *   PostingRuleLine::create('8000', '100')->vatCode('VH')->description('Monthly fee');
 *
 * A line without ratio only proposes the account, cost center and VAT code: the user enters
 * the amount when booking. Twinfield returns such lines with an empty `<ratio/>`.
 */
final readonly class PostingRuleLine
{
    /**
     * @param ?numeric-string $ratio
     */
    private function __construct(
        /** General ledger account (dimension 1), e.g. `8000`. */
        public string $generalLedger,
        /**
         * Share of the posting rule, e.g. `78.4`; the ratios of a rule add up to 100. Null for a
         * line without fixed share.
         */
        public ?string $ratio,
        /** Cost center (dimension 2); null when not set. */
        public ?string $costCenter = null,
        /** Project or asset (dimension 3); null when not set. */
        public ?string $project = null,
        public ?string $vatCode = null,
        /** At most 40 characters. */
        public ?string $description = null,
        /** Destination company for intercompany postings; null for the same company. */
        public ?string $company = null,
    ) {
        XmlValue::code($generalLedger);

        if ($ratio !== null && preg_match('~^\d+(?:\.\d+)?$~', $ratio) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a ratio: use a positive numeric string with a dot, e.g. "78.4".',
                $ratio,
            ));
        }

        foreach ([$costCenter, $project] as $code) {
            if ($code !== null) {
                XmlValue::code($code);
            }
        }

        if ($vatCode !== null) {
            XmlValue::vatCode($vatCode);
        }

        if ($description !== null) {
            XmlValue::maxLength('posting rule line description', $description, 40);
        }

        if ($company !== null) {
            XmlValue::maxLength('posting rule line company', $company, 16);
        }
    }

    /**
     * @param string $generalLedger General ledger account (dimension 1), e.g. `8000`.
     * @param ?numeric-string $ratio Share of the rule, e.g. `'100'` or `'78.4'`; null for a
     *     line without fixed share.
     */
    public static function create(string $generalLedger, ?string $ratio): self
    {
        return new self($generalLedger, $ratio);
    }

    /**
     * From a `<line>` element of a posting rule.
     *
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        $ratio = $line->child('ratio');
        $ratioText = $ratio->text();

        // Empty for a line without fixed share, as seen in live posting rules of suppliers.
        if ($ratioText !== '' && (!is_numeric($ratioText) || preg_match('~^\d+(?:\.\d+)?$~', $ratioText) !== 1)) {
            throw $ratio->invalid('a ratio');
        }

        return new self(
            generalLedger: $line->string('dimension1'),
            ratio: $ratioText === '' ? null : $ratioText,
            // Twinfield leaves out <dimension2> and <dimension3> when they are not set.
            costCenter: $line->nullableChild('dimension2')?->text() ?: null,
            project: $line->nullableChild('dimension3')?->text() ?: null,
            vatCode: $line->nullableString('vatcode'),
            description: $line->nullableString('description'),
            company: $line->nullableString('office'),
        );
    }

    public function costCenter(?string $costCenter): self
    {
        return $this->with(['costCenter' => $costCenter === '' ? null : $costCenter]);
    }

    public function project(?string $project): self
    {
        return $this->with(['project' => $project === '' ? null : $project]);
    }

    public function vatCode(?string $vatCode): self
    {
        return $this->with(['vatCode' => $vatCode === '' ? null : $vatCode]);
    }

    public function description(?string $description): self
    {
        return $this->with(['description' => $description === '' ? null : $description]);
    }

    /**
     * Destination company for intercompany postings.
     */
    public function company(?string $company): self
    {
        return $this->with(['company' => $company === '' ? null : $company]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
