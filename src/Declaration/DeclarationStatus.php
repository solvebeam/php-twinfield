<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Declaration;

use InvalidArgumentException;

final readonly class DeclarationStatus
{
    public function __construct(
        public DeclarationStatusStep $step,
        /** In the language of the user, e.g. `Goedgekeurd`. Only for showing to the user. */
        public string $description,
        /** Null when empty or left out, as in all returns seen live. */
        public ?string $extraInformation = null,
    ) {
        if ($description === '') {
            throw new InvalidArgumentException('Status description must not be empty.');
        }
    }
}
