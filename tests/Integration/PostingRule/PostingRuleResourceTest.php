<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\PostingRule;

use DateTimeImmutable;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API posting rules (read-only) of TWINFIELD_COMPANY.
 */
final class PostingRuleResourceTest extends IntegrationTestCase
{
    public function testListsAllPostingRules(): void
    {
        $this->requireScopes(Scope::ReadCustomers, Scope::ReadSuppliers);

        $rules = iterator_to_array(
            $this->twinfield->postingRules()->modifiedSince(new DateTimeImmutable('2000-01-01'))->limit(100)->all(),
            false,
        );
        $ids = array_map(static fn ($rule): string => $rule->id, $rules);

        self::assertSame($ids, array_values(array_unique($ids)), 'Each rule once.');
    }
}
