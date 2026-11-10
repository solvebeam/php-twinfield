<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\VatCode;

use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API VAT codes (read-only) of TWINFIELD_COMPANY, compared with the XML web services.
 */
final class VatCodeDetailsTest extends IntegrationTestCase
{
    public function testReadsTheSameVatCodesAsTheXmlWebServices(): void
    {
        $this->requireScopes(Scope::ReadVat);

        $all = iterator_to_array($this->twinfield->vatCodes()->searchDetails()->all(), false);

        self::assertNotEmpty($all);

        $details = $this->twinfield->vatCodes()->getDetails($all[0]->id);
        $vatCode = $this->twinfield->vatCodes()->get($details->code);

        self::assertEquals($all[0], $details);
        self::assertSame($vatCode->name, $details->name);
        self::assertSame($vatCode->type, $details->type);
        self::assertCount(count($vatCode->percentages), $details->rates);
    }
}
