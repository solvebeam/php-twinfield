<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Authentication;

use SolveBeam\Twinfield\Soap\SoapHeader;
use SolveBeam\Twinfield\Soap\SoapName;

#[SoapHeader(namespace: 'http://www.twinfield.com/', element: 'Header')]
final readonly class AuthenticationHeader
{
    public function __construct(
        #[SoapName('AccessToken')]
        public string $accessToken,
        #[SoapName('CompanyCode')]
        public string $companyCode,
    ) {}
}
