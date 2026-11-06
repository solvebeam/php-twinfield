<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Offices the logged in user has access to.
 *
 *   foreach ($twinfield->offices()->list() as $office) {
 *       echo $office->code, ' ', $office->name;
 *   }
 */
final readonly class OfficeResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * All offices, over all Finder pages.
     *
     * @return list<Office>
     */
    public function list(): array
    {
        $offices = [];

        // Finder columns for offices: code, name, … (names depend on the user's language).
        foreach ($this->twinfield->finder(FinderType::Offices)->all() as $index => $row) {
            $offices[] = self::fromFinderRow($index, $row);
        }

        return $offices;
    }

    /**
     * @param list<string> $row
     */
    private static function fromFinderRow(int $index, array $row): Office
    {
        $code = $row[0] ?? '';
        $name = $row[1] ?? '';

        if ($code === '' || $name === '') {
            throw new UnexpectedResponseException(sprintf(
                'Finder office row %d has no code or name: %s.',
                $index,
                json_encode($row, JSON_THROW_ON_ERROR),
            ));
        }

        return new Office(code: $code, name: $name);
    }

    public function find(string $code): ?Office
    {
        foreach ($this->list() as $office) {
            if ($office->code === $code) {
                return $office;
            }
        }

        return null;
    }
}
