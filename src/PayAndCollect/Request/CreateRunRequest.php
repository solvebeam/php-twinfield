<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect\Request;

use SolveBeam\Twinfield\Data\ArrayOfArrayOfString;
use SolveBeam\Twinfield\Soap\SoapName;

/**
 * `CreateRunRequest` of CreateRun2, and `CreateRunAsyncRequest` of CreateRunAsync: the same fields,
 * in WSDL order. Null fields are left out; Twinfield then uses the setting of the pay type.
 *
 * @internal
 */
final readonly class CreateRunRequest
{
    public function __construct(
        #[SoapName('Office')]
        public string $office,
        #[SoapName('PayType')]
        public string $payType,
        /** `xs:dateTime`. */
        #[SoapName('PayDate')]
        public string $payDate,
        /** `yyyy/pp`. */
        #[SoapName('Period')]
        public string $period,
        #[SoapName('Method')]
        public ?string $method,
        /** `true` or `false`. */
        #[SoapName('DirectRemove')]
        public ?string $directRemove,
        #[SoapName('BankCode')]
        public ?string $bankCode,
        #[SoapName('DimensionFrom')]
        public ?string $dimensionFrom,
        #[SoapName('DimensionTo')]
        public ?string $dimensionTo,
        /** `xs:dateTime`. */
        #[SoapName('MaxDuedate')]
        public ?string $maxDueDate,
        #[SoapName('InvoiceNumber')]
        public ?string $invoiceNumber,
        #[SoapName('Currency')]
        public ?string $currency,
        #[SoapName('DiscountDaysTolerance')]
        public int $discountDaysTolerance,
        #[SoapName('CustomSettings')]
        public ?ArrayOfArrayOfString $customSettings,
    ) {
    }
}
