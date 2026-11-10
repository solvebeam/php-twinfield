<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DateTimeImmutable;

/**
 * A rate of a VAT code from a start date on (REST API). The rate with the latest start date before
 * the invoice or transaction date applies.
 */
final readonly class VatRate
{
    /**
     * @param list<VatCodeTranslation> $translations
     * @param list<VatAllocationRule>  $allocationRules
     */
    public function __construct(
        /** GUID of the rate. */
        public string $id,
        public string $name,
        public string $shortName,
        public DateTimeImmutable $startDate,
        public Percentage $percentage,
        /** GUID of the account for VAT on payment discount; null when the country has none. */
        public ?string $discountAccountId,
        /** GUID of the account for written off VAT; null when the country has none. */
        public ?string $writeOffAccountId,
        public array $translations,
        public array $allocationRules,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastModifiedAt,
        /** Code of the user who last changed it. */
        public string $modifiedBy,
    ) {
    }
}
