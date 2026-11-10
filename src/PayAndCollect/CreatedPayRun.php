<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * Result of PayAndCollectResource::create(): the new run, with the warnings and informational
 * messages of the selection, e.g. about invoices that were skipped.
 */
final readonly class CreatedPayRun
{
    /**
     * @param list<PayAndCollectMessage> $messages
     */
    public function __construct(
        public PayRun $run,
        public array $messages,
    ) {
    }
}
