<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

/**
 * Type of an address of a customer or supplier (`address/@type`).
 */
enum AddressType: string
{
    case Invoice = 'invoice';
    case Postal = 'postal';
    case Contact = 'contact';
}
