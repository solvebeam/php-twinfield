<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use InvalidArgumentException;

/**
 * A dimension on a transaction line (`dim1`, `dim2` or `dim3`), with the name Twinfield adds, e.g.
 * `<dim2 name="Example customer" type="DEB">1000</dim2>`.
 */
final readonly class DimensionReference
{
    public function __construct(
        /** Code, e.g. `1300` (a ledger account) or `1000` (a customer). */
        public string $code,
        /** Dimension type, e.g. `BAS` (balance sheet), `PNL`, `DEB` or `CRD`; null when Twinfield leaves it out. */
        public ?string $type,
        /** Null when Twinfield gives no name. */
        public ?string $name,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Dimension code must not be empty.');
        }
    }

    public function isCustomer(): bool
    {
        return $this->type === 'DEB';
    }

    public function isSupplier(): bool
    {
        return $this->type === 'CRD';
    }
}
