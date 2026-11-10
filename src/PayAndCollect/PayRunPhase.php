<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * Phase of a pay run, in the order a run goes through them.
 */
enum PayRunPhase: string
{
    /** Transactions are selected and the run is created. */
    case Select = 'Select';

    /** The run is edited (modify()). */
    case Manipulate = 'Manipulate';

    /** The run is authorised: its data can no longer be changed. */
    case Authorise = 'Authorise';

    /** The run is posted to the in-transfer ledger and matched with the invoices. */
    case Post = 'Post';

    /** A payment file is created. */
    case Pay = 'Pay';

    /** The run is finalised. */
    case Finalise = 'Finalise';
}
