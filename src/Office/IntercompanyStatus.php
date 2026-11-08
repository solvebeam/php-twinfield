<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

/**
 * Whether the office has intercompany relations (`intercompany/@status`).
 */
enum IntercompanyStatus: string
{
    case None = 'none';

    /** Outgoing and incoming relations. */
    case Both = 'both';

    /** Only outgoing relations; not seen in live responses yet. */
    case Out = 'out';

    /** Only incoming relations; not seen in live responses yet. */
    case In = 'in';
}
