<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

/**
 * Result of PayAndCollectResource::createFiles(): the payment files and the new version of the run.
 * Twinfield creates more than one file when a bank limits the number of transactions per file.
 */
final readonly class PayRunFiles
{
    /**
     * @param list<string> $files Contents of each file (decoded), e.g. SEPA XML.
     */
    public function __construct(
        public array $files,
        public PayRunVersion $version,
    ) {
    }
}
