<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\LedgerAccount;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;

/**
 * Maps the JSON of the REST API onto LedgerAccount.
 *
 * @internal
 */
final class LedgerAccountReader
{
    public static function read(JsonValue $json, CompanyReference $company): LedgerAccount
    {
        return new LedgerAccount(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            name: $json->string('name'),
            type: $json->enum('type', LedgerAccountType::class),
            subanalysis: $json->enum('subanalysis', Subanalysis::class),
            defaultParentAccountId: $json->nullableUuid('defaultParentAccountId'),
        );
    }
}
