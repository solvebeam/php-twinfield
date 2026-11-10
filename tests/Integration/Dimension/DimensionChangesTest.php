<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Dimension;

use DateTimeImmutable;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Rest\RestException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Live REST API changes of customers and suppliers (read-only) of TWINFIELD_COMPANY.
 */
final class DimensionChangesTest extends IntegrationTestCase
{
    public function testListsChangedSuppliersAndMatchesTheirUid(): void
    {
        $this->requireScopes(Scope::ReadSuppliers);

        $page = $this->twinfield->suppliers()->changes(new DateTimeImmutable('2000-01-01'))->limit(5)->get();

        if ($page->items === []) {
            self::markTestSkipped('The company has no suppliers.');
        }

        $uids = [];

        foreach ($this->twinfield->suppliers()->search()->all() as $summary) {
            try {
                $uids[] = strtolower($this->twinfield->suppliers()->get($summary->code)->uid);
            } catch (UnexpectedResponseException) {
                // Not about changes: see TODO.md (posting rule line without dimension1).
                continue;
            }

            if (count($uids) >= 5) {
                break;
            }
        }

        self::assertNotEmpty(array_intersect($uids, array_map(static fn ($change) => $change->id, iterator_to_array(
            $this->twinfield->suppliers()->changes(new DateTimeImmutable('2000-01-01'))->all(),
            false,
        ))));
    }

    public function testListsChangedCustomers(): void
    {
        $this->requireScopes(Scope::ReadCustomers);

        try {
            $page = $this->twinfield->customers()->changes(new DateTimeImmutable('2000-01-01'))->limit(5)->get();
        } catch (RestException $exception) {
            // Some companies answer every request with a server error; see Documentation differences.
            self::assertSame(500, $exception->status);
            self::markTestSkipped('Twinfield answers customer changes of this company with HTTP 500.');
        }

        self::assertGreaterThanOrEqual(count($page->items), $page->filteredTotal);
    }
}
