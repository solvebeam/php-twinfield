<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A user in search results: code and name. Use `users()->get($code)` for all settings.
 */
final readonly class UserSummary
{
    public function __construct(
        /** Organisation the user belongs to; the user code is only unique within it. */
        public Organisation $organisation,
        /** User code, e.g. `JDOE`. */
        public string $code,
        public string $name,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('User code and name must not be empty.');
        }
    }

    /**
     * From a Finder `USR` row; columns: code, name (column names depend on the user's language).
     *
     * @param list<string> $row
     *
     * @internal
     */
    public static function fromFinderRow(array $row, int $index, Organisation $organisation): self
    {
        if (count($row) !== 2 || $row[0] === '' || $row[1] === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder user row %d is not a code and a name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new self($organisation, $row[0], $row[1]);
    }
}
