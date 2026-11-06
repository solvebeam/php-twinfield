<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Twinfield;

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
        foreach ($this->twinfield->finder(FinderType::Offices)->all() as $row) {
            $offices[] = new Office(code: $row[0] ?? '', name: $row[1] ?? '');
        }

        return $offices;
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
