<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

use SolveBeam\Twinfield\Soap\SoapName;

#[SoapName('Header')]
final readonly class AuthenticationHeader
{
    public function __construct(
        #[SoapName('AccessToken')]
        public string $accessToken,
        /** Omitted from the request when null, e.g. to list all offices. */
        #[SoapName('CompanyCode')]
        public ?string $companyCode = null,
    ) {
    }
}
