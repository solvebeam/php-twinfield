<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Version of a pay run: how often it was changed (`touched`) and when (`modified`). Every step that
 * changes a run needs the version it was last read with, and returns the new one:
 *
 *   $version = $payAndCollect->modify($modification);
 *   $version = $payAndCollect->authorise($run, $version);
 *
 * When someone else changed the run in between, Twinfield refuses the step.
 */
final readonly class PayRunVersion
{
    /**
     * @param string $modifiedValue `modified` exactly as Twinfield sent it; it is sent back
     *                              unchanged, so no precision or time zone is lost.
     * @param list<PayAndCollectMessage> $messages Warnings and informational messages of the step
     *                                             that returned this version; empty for a run that
     *                                             was read.
     */
    public function __construct(
        public int $touched,
        public DateTimeImmutable $modified,
        public string $modifiedValue,
        public array $messages = [],
    ) {
        if ($touched < 0) {
            throw new InvalidArgumentException(sprintf('Touched must be 0 or more, got %d.', $touched));
        }

        if ($modifiedValue === '') {
            throw new InvalidArgumentException('The modified value of a pay run version must not be empty.');
        }
    }
}
