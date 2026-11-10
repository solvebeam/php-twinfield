<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankFeed;

use SolveBeam\Twinfield\Twinfield;

/**
 * Bank feeds of the company (REST API, Feeds). Read-only; scope `readBankAccounts`.
 *
 *   foreach ($twinfield->company('1000')->bankFeeds()->psd2()->inAllCompanies()->all() as $feed) {
 *       echo $feed->account->iban, ' expires ', $feed->expiresOn->format('Y-m-d'), PHP_EOL;
 *   }
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Feeds
 */
final readonly class BankFeedResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * PSD2 feeds: of the company of the client, or of all companies with `inAllCompanies()`.
     */
    public function psd2(): Psd2FeedSearch
    {
        return new Psd2FeedSearch(
            $this->twinfield,
            '/organisations/{organisationId}/companies/{companyId}/banks/feeds/psd2',
            ['requestedCompanies' => 'current'],
        );
    }
}
