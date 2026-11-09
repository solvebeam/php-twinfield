<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Organisation;

use InvalidArgumentException;

/**
 * A Twinfield organisation: usually an accountancy firm or a business with its own Twinfield
 * environment. It has companies (offices, administrations) and users, whose codes are only
 * unique within the organisation: company `1000` or user `JDOE` can exist in several
 * organisations and are then different companies and users.
 *
 * The organisation comes from the access token (claims `twf.organisationId` and
 * `twf.organisationCode`); Twinfield does not repeat it in API responses. Every model the SDK
 * reads carries it, e.g. `$office->organisation` or `$customer->company->organisation`.
 *
 *   echo $twinfield->organisation()->code, PHP_EOL;
 *
 * Use `id` as the key when you store organisations, e.g. in a shadow database; `code` is for
 * display.
 */
final readonly class Organisation
{
    public function __construct(
        /** GUID of the organisation (`twf.organisationId`), e.g. `0b6a5f6e-1111-2222-3333-444455556666`. */
        public string $id,
        /** Code of the organisation (`twf.organisationCode`), e.g. `EXAMPLE`. */
        public string $code,
    ) {
        if ($id === '' || $code === '') {
            throw new InvalidArgumentException('Organisation ID and code must not be empty.');
        }
    }

    /**
     * True for the same organisation: compares the IDs.
     */
    public function equals(self $other): bool
    {
        return strcasecmp($this->id, $other->id) === 0;
    }
}
