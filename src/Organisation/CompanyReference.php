<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Organisation;

use InvalidArgumentException;

/**
 * The company (office, administration) a model belongs to: the organisation and the company
 * code. A company code is only unique within its organisation, so you need both to tell
 * companies apart, e.g. as the key of a shadow database.
 *
 *   $customer = $twinfield->customers()->get('1000');
 *
 *   echo $customer->company->organisation->code, ' ', $customer->company->code, PHP_EOL;
 *
 * Pass it to `$twinfield->company()` to get a client for the company.
 */
final readonly class CompanyReference
{
    public function __construct(
        public Organisation $organisation,
        /** Company (office) code, e.g. `1000`. */
        public string $code,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Company code must not be empty.');
        }
    }

    /**
     * True for the same company: the same organisation and company code.
     */
    public function equals(self $other): bool
    {
        return $this->organisation->equals($other->organisation) && $this->code === $other->code;
    }
}
