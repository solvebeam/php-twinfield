<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\BankFeed;

use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API PSD2 feeds (read-only).
 */
final class BankFeedResourceTest extends IntegrationTestCase
{
    public function testListsPsd2Feeds(): void
    {
        $this->requireScopes(Scope::ReadBankAccounts);

        $page = $this->twinfield->bankFeeds()->psd2()->inAllCompanies()->get();

        self::assertGreaterThanOrEqual(count($page->items), $page->filteredTotal);
    }
}
