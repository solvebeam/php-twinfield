<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PostingRule;

use DateTimeImmutable;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A posting rule of a customer or supplier (REST API): how an invoice of the relation is divided over
 * accounts. In the XML web services posting rules are part of the dimension (`postingrules`).
 */
final readonly class PostingRule
{
    /**
     * @param list<PostingRuleLine> $lines
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the posting rule. */
        public string $id,
        public PostingRuleType $type,
        /** GUID of the customer or supplier, the `uid` of the dimension. */
        public string $accountId,
        /** GUID of the currency; null for all currencies. */
        public ?string $currencyId,
        /** Amount the rule applies to; null for any amount. */
        public ?Amount $amount,
        public ?string $description,
        public array $lines,
        public int $version,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        /** Code of the user who last changed it, e.g. `JDOE`. */
        public string $modifiedBy,
    ) {
        Guid::of($id);
        Guid::of($accountId, 'Account ID');
    }
}
