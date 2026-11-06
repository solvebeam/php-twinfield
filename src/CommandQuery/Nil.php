<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CommandQuery;

/**
 * An explicitly empty field: sent as `<a:Iban i:nil="true"/>`.
 *
 * Use it for fields the WSDL marks as required but nillable (no `minOccurs="0"`):
 * the WCF service refuses a request where such a field is left out. `null` leaves a field out.
 *
 *   new Command('ChangeBankBookBankAccount', ['Code' => 'ING', 'BankAccount' => ['Bic' => Nil::Value, …]]);
 */
enum Nil
{
    case Value;
}
