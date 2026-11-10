<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankFeed;

use DateTimeInterface;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the PSD2 feeds, created by `bankFeeds()->psd2()`.
 *
 * @extends RestSearch<Psd2Feed>
 */
final readonly class Psd2FeedSearch extends RestSearch
{
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    /**
     * The feeds of all companies the user has access to, not only of the company of the client.
     */
    public function inAllCompanies(): self
    {
        return $this->with('requestedCompanies', 'all');
    }

    /**
     * Only feeds whose consent expires between these moments, e.g. to warn before it does.
     */
    public function expiresBetween(?DateTimeInterface $from, ?DateTimeInterface $to): self
    {
        return $this->with('expiresOnFrom', $from?->format(DATE_ATOM))->with('expiresOnTo', $to?->format(DATE_ATOM));
    }

    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): Psd2Feed
    {
        $account = $item->object('feedAccount');

        return new Psd2Feed(
            id: $item->uuid('id'),
            consentOn: $item->timestamp('consentOn'),
            expiresOn: $item->timestamp('expiresOn'),
            account: new Psd2FeedAccount(
                id: $account->uuid('id'),
                bankAccountId: $account->uuid('bankAccountId'),
                companyId: $account->uuid('companyId'),
                companyCode: $account->string('companyCode'),
                companyName: $account->string('companyName'),
                iban: $account->string('iban'),
            ),
        );
    }
}
