<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PostingRule;

use DateTimeInterface;
use SolveBeam\Twinfield\Twinfield;

/**
 * Posting rules of the customers and suppliers of the company (REST API). Read-only; scope
 * `readCustomers` or `readSuppliers`.
 *
 *   $rules = $twinfield->company('1000')->postingRules()->modifiedSince(new DateTimeImmutable('-1 day'));
 *
 *   foreach ($rules->all() as $rule) {
 *       echo $rule->type->value, ' ', $rule->accountId, ': ', count($rule->lines), ' lines', PHP_EOL;
 *   }
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Posting32Rules
 */
final readonly class PostingRuleResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Posting rules changed on or after this moment; Twinfield requires a moment. Use a date long
     * ago, e.g. `2000-01-01`, for all posting rules.
     */
    public function modifiedSince(DateTimeInterface $since): PostingRuleSearch
    {
        return new PostingRuleSearch(
            $this->twinfield,
            '/organisations/{organisationId}/companies/{companyId}/bookkeeping/postingrule',
            ['modifiedSince' => $since->format(DATE_ATOM)],
        );
    }
}
