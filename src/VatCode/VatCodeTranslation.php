<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

/**
 * Name of a VAT code or VAT rate in another language (REST API).
 */
final readonly class VatCodeTranslation
{
    public function __construct(
        /** Culture code, e.g. `en-GB`. */
        public string $language,
        public string $name,
        public ?string $shortName,
        /** Text on invoices; only VAT codes have one, rates don't. */
        public ?string $invoiceText,
    ) {
    }
}
