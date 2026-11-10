<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use SolveBeam\Twinfield\Guid;

/**
 * Company GUIDs by company code, shared by the clients of one `Twinfield::create()`, so the SDK looks
 * them up at most once.
 *
 * @internal
 */
final class CompanyIds
{
    /** @var array<string, string> */
    private array $ids = [];

    public function get(string $code): ?string
    {
        return $this->ids[$code] ?? null;
    }

    public function set(string $code, string $id): void
    {
        $this->ids[$code] = Guid::of($id, 'Company ID');
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }
}
