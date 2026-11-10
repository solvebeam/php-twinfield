<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Docs;

/**
 * The "XML" sections of the example READMEs: per example the Twinfield documentation page and
 * the calls to show. Run `composer docs:xml` after changing this file.
 */
final class ExampleXmlDefinitions
{
    private const DOCS = 'https://developers.twinfield.com/documentation/api/';

    /**
     * Twinfield documentation page per example directory.
     *
     * @return array<string, array{string, string}> Directory => [title, URL].
     */
    public static function pages(): array
    {
        return [
            'bank-transactions' => ['Bank transactions', self::DOCS . 'transaction-data/bank-transactions/'],
            'browse-data' => ['Browse data', self::DOCS . 'request-data/browse-data/'],
            'cash-transactions' => ['Cash transactions', self::DOCS . 'transaction-data/cash-transactions/'],
            'deleted-transactions' => ['Deleted transactions', self::DOCS . 'transaction-data/deleted-transactions/'],
            'electronic-bank-statements' => [
                'Electronic bank statements',
                self::DOCS . 'transaction-data/electronic-bank-statements/',
            ],
            'journal-transactions' => ['Journal transactions', self::DOCS . 'transaction-data/journal-transactions/'],
            'purchase-transactions' => ['Purchase transactions', self::DOCS . 'transaction-data/purchase-transactions/'],
            'reversal-transactions' => ['Reversal transactions', self::DOCS . 'transaction-data/reversal-transactions/'],
            'sales-invoices' => ['Sales invoices', self::DOCS . 'transaction-data/sales-invoices/'],
            'sales-transactions' => ['Sales transactions', self::DOCS . 'transaction-data/sales-transactions/'],
            'transaction-blocked-values' => [
                'Transaction blocked value',
                self::DOCS . 'transaction-data/transaction-blocked-value/',
            ],
        ];
    }

    /**
     * @return array<string, list<XmlExample>> Example (e.g. `journal-transactions/read-journal-transaction`) => calls.
     */
    public static function examples(): array
    {
        $bank = self::DOCS . 'transaction-data/bank-transactions/';
        $browse = self::DOCS . 'request-data/browse-data/';
        $cash = self::DOCS . 'transaction-data/cash-transactions/';
        $journal = self::DOCS . 'transaction-data/journal-transactions/';
        $purchase = self::DOCS . 'transaction-data/purchase-transactions/';
        $reversal = self::DOCS . 'transaction-data/reversal-transactions/';
        $invoices = self::DOCS . 'transaction-data/sales-invoices/';
        $sales = self::DOCS . 'transaction-data/sales-transactions/';
        $statements = self::DOCS . 'transaction-data/electronic-bank-statements/';
        $blocked = self::DOCS . 'transaction-data/transaction-blocked-value/';

        $savedLikeRead = static fn (string $read): string => sprintf(
            'Twinfield answers with the saved transaction, with its number and the names of the codes, in the '
            . 'same format as [%s](%s).',
            ucfirst(str_replace('-', ' ', basename($read))),
            '../' . basename($read) . '/',
        );

        return [
            // Bank transactions.
            'bank-transactions/bank-costs-with-vat' => [
                new XmlExample(
                    'Create',
                    $bank . '#createupdate',
                    <<<'PHP'
                        $twinfield->bankTransactions()->create(
                            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                                ->pay('4500', '10.00', vatCode: 'IH', vatValue: '2.10', description: 'Bank costs November')
                                ->autoBalanceVat(),
                        );
                        PHP,
                    ['bank-transaction-save'],
                    showResponse: false,
                    responseNote: $savedLikeRead('bank-transactions/create-bank-transaction'),
                ),
            ],
            'bank-transactions/bulk-import-with-errors' => [
                new XmlExample(
                    'Create',
                    $bank . '#createupdate',
                    <<<'PHP'
                        $base = BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'));

                        $twinfield->bankTransactions()->saveMany(
                            $base->receive('1300', '50.00', customer: '1000'),
                            $base->receive('1300', '75.00', customer: '9999'),
                        );
                        PHP,
                    ['bank-transaction-save-many-partial'],
                ),
            ],
            'bank-transactions/create-bank-transaction' => [
                new XmlExample(
                    'Create',
                    $bank . '#createupdate',
                    <<<'PHP'
                        $twinfield->bankTransactions()->create(
                            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                                ->statement(246, '1397.46')
                                ->receive('1300', '121.00', customer: '1000', invoice: '2026-0043', description: 'Invoice 2026-0043'),
                        );
                        PHP,
                    ['bank-transaction-save'],
                ),
            ],
            'bank-transactions/delete-bank-transaction' => [
                new XmlExample(
                    'Delete',
                    $bank . '#delete',
                    "\$twinfield->bankTransactions()->delete('BNK', 202600046, 'Imported twice');",
                    ['bank-transaction-delete'],
                ),
            ],
            'bank-transactions/import-bank-statement' => [
                new XmlExample(
                    'Create',
                    $bank . '#createupdate',
                    <<<'PHP'
                        $twinfield->bankTransactions()->create(
                            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                                ->statement(245, startValue: '1000.00')
                                ->description('Statement 245')
                                ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042', description: 'Example customer Invoice 2026-0042')
                                ->pay('4500', '12.10', description: 'Example bank Monthly fee November'),
                        );
                        PHP,
                    ['bank-transaction-save'],
                    showResponse: false,
                    responseNote: $savedLikeRead('bank-transactions/create-bank-transaction'),
                ),
            ],
            'bank-transactions/post-bank-transaction' => [
                new XmlExample(
                    'Post',
                    $bank . '#post',
                    "\$twinfield->bankTransactions()->post('BNK', 202600046);",
                    ['bank-transaction-post'],
                ),
            ],
            'bank-transactions/read-bank-transaction' => [
                new XmlExample(
                    'Read',
                    $bank . '#read',
                    "\$twinfield->bankTransactions()->get('BNK', 202600045);",
                    ['bank-transaction-read'],
                ),
            ],
            'bank-transactions/split-payment-over-invoices' => [
                new XmlExample(
                    'Create',
                    $bank . '#createupdate',
                    <<<'PHP'
                        $twinfield->bankTransactions()->create(
                            BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
                                ->receive('1300', '400.00', customer: '1001', invoice: '2026-0039', description: '2026-0039')
                                ->receive('1300', '605.00', customer: '1001', invoice: '2026-0040', description: '2026-0040')
                                ->receive('1300', '245.00', customer: '1001', invoice: '2026-0041', description: '2026-0041'),
                        );
                        PHP,
                    ['bank-transaction-save'],
                    showResponse: false,
                    responseNote: $savedLikeRead('bank-transactions/create-bank-transaction'),
                ),
            ],
            'bank-transactions/update-bank-transaction' => [
                new XmlExample(
                    'Update',
                    $bank . '#createupdate',
                    <<<'PHP'
                        $bank = $twinfield->bankTransactions();
                        $transaction = $bank->get('BNK', 202600045);

                        $bank->update($transaction->edit()->description('Statement 245 (checked)'));
                        PHP,
                    ['bank-transaction-read', 'bank-transaction-read', 'bank-transaction-read'],
                    showResponse: false,
                    responseNote: '`update()` reads the transaction first, to check that it exists, and then saves the whole '
                        . 'transaction again. ' . $savedLikeRead('bank-transactions/read-bank-transaction'),
                ),
            ],

            // Browse data.
            'browse-data/000-general-ledger-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::GeneralLedgerTransactions)
                            ->columns(
                                'fin.trs.head.yearperiod',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.status',
                                'fin.trs.line.dim1',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.description',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->sortBy('fin.trs.line.dim1')
                            ->sortBy('fin.trs.head.yearperiod')
                            ->sortBy('fin.trs.head.number', SortOrder::Descending)
                            ->get();
                        PHP,
                    ['browse-000'],
                ),
            ],
            'browse-data/010-transactions-to-be-matched' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TransactionsToBeMatched)
                            ->columns(
                                'fin.trs.line.dim1',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.openbasevaluesigned',
                                'fin.trs.line.matchstatus',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-010'],
                ),
            ],
            'browse-data/020-transaction-list' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TransactionList)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.status',
                                'fin.trs.head.date',
                                'fin.trs.head.inpdate',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim2',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.description',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.head.code', 'VRK')
                            ->get();
                        PHP,
                    ['browse-020'],
                ),
            ],
            'browse-data/030_1-general-ledger-details-v1' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::GeneralLedgerDetails)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.date',
                                'fin.trs.head.origin',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1type',
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2type',
                                'fin.trs.line.debitcredit',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.vatcode',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-030_1'],
                ),
            ],
            'browse-data/030_2-general-ledger-details-v2' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::GeneralLedgerDetailsV2)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.relation',
                                'fin.trs.head.relationname',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim2',
                                'fin.trs.line.valuesigned',
                                'fin.trs.head.regime',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.head.regime', Regime::Fiscal)
                            ->get();
                        PHP,
                    ['browse-030_2'],
                ),
            ],
            'browse-data/030_3-general-ledger-details' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.date',
                                'fin.trs.head.status',
                                'fin.trs.head.relation',
                                'fin.trs.head.username',
                                'fin.trs.head.modified',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim2',
                                'fin.trs.line.debitcredit',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.vatcode',
                                'fin.trs.line.description',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-030_3'],
                ),
            ],
            'browse-data/031-general-ledger-intercompany' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::GeneralLedgerIntercompany)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.date',
                                'fin.trs.line.dim1',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.destination.officename',
                                'fin.trs.line.destination.dim1',
                                'fin.trs.line.destination.dim1name',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-031'],
                ),
            ],
            'browse-data/031_2-general-ledger-intercompany-v2' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::GeneralLedgerIntercompanyV2)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.date',
                                'fin.trs.line.dim1',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.destination.officename',
                                'fin.trs.line.destination.dim1',
                                'fin.trs.line.destination.dim1name',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-031_2'],
                ),
            ],
            'browse-data/040_1-annual-report-totals' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::AnnualReportTotals)
                            ->columns(
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.line.dim1type',
                                'fin.trs.line.dim1group1',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'final')
                            ->equal('fin.trs.head.balanceregimeprompt', Regime::Generic)
                            ->get();
                        PHP,
                    ['browse-040_1'],
                ),
            ],
            'browse-data/050_1-annual-report-ytd' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::AnnualReportYearToDate)
                            ->columns(
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.line.dim1type',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/06')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.head.balanceregimeprompt', Regime::Generic)
                            ->get();
                        PHP,
                    ['browse-050_1'],
                ),
            ],
            'browse-data/060-annual-report-multicurrency' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::AnnualReportTotalsMulticurrency)
                            ->columns(
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.head.curcode',
                                'fin.trs.line.debitcredit',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'final')
                            ->equal('fin.trs.head.balanceregimeprompt', Regime::Generic)
                            ->get();
                        PHP,
                    ['browse-060'],
                ),
            ],
            'browse-data/100-customer-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::CustomerTransactions)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.head.date',
                                'fin.trs.line.datedue',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.openbasevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-100'],
                ),
            ],
            'browse-data/130_1-customers' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::Customers)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.head.customer.creditlimit',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.line.datedue',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.openbasevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-130_1'],
                ),
            ],
            'browse-data/130_2-customers-v2' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::CustomersV2)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.openvaluesigned',
                                'fin.trs.line.matchstatus',
                                'fin.trs.line.matchdate',
                                'fin.trs.line.modified',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->betweenDateTimes('fin.trs.line.modified', new DateTimeImmutable('2026-03-01 00:00 UTC'), new DateTimeImmutable('2026-03-31 00:00 UTC'))
                            ->get();
                        PHP,
                    ['browse-130_2'],
                ),
            ],
            'browse-data/130_3-customers-v3' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::CustomersV3)
                            ->columns(
                                'fin.trs.head.customer.respuser',
                                'fin.trs.head.customer.respusername',
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.line.datedue',
                                'fin.trs.line.openbasevaluesigned',
                                'fin.trs.line.payrun',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-130_3'],
                ),
            ],
            'browse-data/164-credit-management' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::CreditManagement)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.line.invnumber',
                                'fin.trs.line.datedue',
                                'fin.trs.line.openbasevaluesigned',
                                'fin.trs.line.actioncode',
                                'fin.trs.line.actionname',
                                'fin.trs.line.actiondate',
                                'fin.trs.line.actionvalue',
                                'fin.trs.line.actionusername',
                            )
                            ->between('fin.trs.line.dim2', '1000', '1000')
                            ->get();
                        PHP,
                    ['browse-164'],
                ),
            ],
            'browse-data/200-supplier-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::SupplierTransactions)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.head.date',
                                'fin.trs.line.datedue',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.openbasevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-200'],
                ),
            ],
            'browse-data/230_1-suppliers' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::Suppliers)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.line.datedue',
                                'fin.trs.line.basevaluesigned',
                                'fin.trs.line.openbasevaluesigned',
                                'fin.trs.line.matchstatus',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-230_1'],
                ),
            ],
            'browse-data/230_2-suppliers-v2' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::SuppliersV2)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.invnumber',
                                'fin.trs.line.datedue',
                                'fin.trs.line.openvaluesigned',
                                'fin.trs.line.matchstatus',
                                'fin.trs.line.payrun',
                            )
                            ->between('fin.trs.head.yearperiod', '2000/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->equal('fin.trs.line.matchstatus', 'available')
                            ->get();
                        PHP,
                    ['browse-230_2'],
                ),
            ],
            'browse-data/300-project-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::ProjectTransactions)
                            ->columns(
                                'fin.trs.line.dim3',
                                'fin.trs.line.dim3name',
                                'fin.trs.head.yearperiod',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.head.status',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim2',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->between('fin.trs.line.dim3', 'P0001', 'P0001')
                            ->get();
                        PHP,
                    ['browse-300'],
                ),
            ],
            'browse-data/301-asset-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::AssetTransactions)
                            ->columns(
                                'fin.trs.line.dim3',
                                'fin.trs.line.dim3name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->get();
                        PHP,
                    ['browse-301'],
                ),
            ],
            'browse-data/302_1-fixed-assets' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::FixedAssets)
                            ->columns(
                                'fin.trs.line.dim3',
                                'fin.trs.line.dim3name',
                                'fin.trs.line.asset.beginyear',
                                'fin.trs.line.asset.beginperiod',
                                'fin.trs.line.asset.depreciationterm',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.dim1',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-302_1'],
                ),
            ],
            'browse-data/400-cash-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::CashTransactions)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.stmnumber',
                                'fin.trs.head.startvalue',
                                'fin.trs.head.closevalue',
                                'fin.trs.head.number',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.bankcode', 'KAS')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-400'],
                ),
            ],
            'browse-data/410-bank-transactions' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::BankTransactions)
                            ->columns(
                                'fin.trs.head.code',
                                'fin.trs.head.stmnumber',
                                'fin.trs.head.startvalue',
                                'fin.trs.head.closevalue',
                                'fin.trs.head.number',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.bankcode', 'BNK')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-410'],
                ),
            ],
            'browse-data/610_1-time-and-expenses-totals' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesTotals)
                            ->columns(
                                'teq.trs.head.project1',
                                'teq.trs.head.project2',
                                'teq.trs.head.user',
                                'teq.trs.line.type',
                                'teq.trs.line.rate.code',
                                'teq.trs.line.billable',
                                'teq.trs.line.quantity',
                                'teq.trs.line.valueint',
                                'teq.trs.line.valueext',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-610_1'],
                ),
            ],
            'browse-data/620-time-and-expenses-multicurrency' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesMulticurrency)
                            ->columns(
                                'fin.trs.line.dim3',
                                'fin.trs.line.dim3name',
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim1',
                                'fin.trs.head.curcode',
                                'fin.trs.line.valuesigned',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->equal('fin.trs.head.status', 'normal')
                            ->get();
                        PHP,
                    ['browse-620'],
                ),
            ],
            'browse-data/650_1-time-and-expenses-details' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesDetails)
                            ->columns(
                                'teq.trs.head.date',
                                'teq.trs.head.user',
                                'teq.trs.head.customer',
                                'teq.trs.head.project1',
                                'teq.trs.head.project2',
                                'teq.trs.head.status',
                                'teq.trs.line.time',
                                'teq.trs.line.time_nb',
                                'teq.trs.line.expense',
                                'teq.trs.line.description',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-650_1'],
                ),
            ],
            'browse-data/651_1-time-and-expenses-totals-per-week' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesTotalsPerWeek)
                            ->columns(
                                'teq.trs.head.year',
                                'teq.trs.head.week',
                                'teq.trs.head.user',
                                'teq.trs.head.project1',
                                'teq.trs.line.time',
                                'teq.trs.line.timevalueext',
                                'teq.trs.line.expense',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-651_1'],
                ),
            ],
            'browse-data/652_1-time-and-expenses-totals-per-period' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesTotalsPerPeriod)
                            ->columns(
                                'teq.trs.head.year',
                                'teq.trs.head.period',
                                'teq.trs.head.user',
                                'teq.trs.head.project1',
                                'teq.trs.line.time',
                                'teq.trs.line.timevalueext',
                                'teq.trs.line.expense',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-652_1'],
                ),
            ],
            'browse-data/660_1-time-and-expenses-billing-details' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesBillingDetails)
                            ->columns(
                                'teq.trs.head.date',
                                'teq.trs.head.customer',
                                'teq.trs.head.project1',
                                'ivb.trs.head.number',
                                'ivb.trs.line.source',
                                'inv.trs.head.number',
                                'teq.trs.line.valueext',
                                'ivb.trs.line.value',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-660_1'],
                ),
            ],
            'browse-data/661_1-time-and-expenses-billing-per-week' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesBillingPerWeek)
                            ->columns(
                                'teq.trs.head.year',
                                'teq.trs.head.week',
                                'teq.trs.head.customer',
                                'teq.trs.head.project1',
                                'teq.trs.line.quantity',
                                'teq.trs.line.valueext',
                                'ivb.trs.line.value',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-661_1'],
                ),
            ],
            'browse-data/662_1-time-and-expenses-billing-per-period' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TimeAndExpensesBillingPerPeriod)
                            ->columns(
                                'teq.trs.head.year',
                                'teq.trs.head.period',
                                'teq.trs.head.customer',
                                'teq.trs.head.project1',
                                'teq.trs.line.quantity',
                                'teq.trs.line.valueext',
                                'ivb.trs.line.value',
                            )
                            ->betweenDates('teq.trs.head.date', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'))
                            ->get();
                        PHP,
                    ['browse-662_1'],
                ),
            ],
            'browse-data/670-transaction-summary' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::TransactionSummary)
                            ->columns(
                                'fin.trs.head.yearperiod',
                                'fin.trs.head.code',
                                'fin.trs.head.count',
                                'fin.trs.line.count',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->get();
                        PHP,
                    ['browse-670'],
                ),
            ],
            'browse-data/680-bank-link-details' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::BankLinkDetails)
                            ->columns(
                                'fin.bnk.transactiontype',
                                'fin.bnk.iban',
                                'fin.bnk.dim1',
                                'fin.bnk.dim1name',
                                'fin.bnk.banklinkstatus',
                            )
                            ->get();
                        PHP,
                    ['browse-680'],
                ),
            ],
            'browse-data/680_2-bank-link-details-v2' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::BankLinkDetailsV2)
                            ->columns(
                                'fin.bnk.transactiontype',
                                'fin.bnk.iban',
                                'fin.bnk.currency',
                                'fin.bnk.accountintegrationtype',
                                'fin.bnk.payments',
                                'fin.bnk.directdebits',
                                'fin.bnk.consentexpirydate',
                            )
                            ->equal('fin.bnk.accountstatus', 'active')
                            ->get();
                        PHP,
                    ['browse-680_2'],
                ),
            ],
            'browse-data/690-vat-return-status' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::VatReturnStatus)
                            ->columns(
                                'fin.vat.decltype',
                                'fin.vat.declyear',
                                'fin.vat.timeframe',
                                'fin.vat.statusname',
                                'fin.vat.sent',
                            )
                            ->equal('fin.vat.declyear', 2026)
                            ->get();
                        PHP,
                    ['browse-690'],
                ),
            ],
            'browse-data/700-hierarchy-access' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::HierarchyAccess)
                            ->columns(
                                'hie.xss.hierarchy',
                                'hie.xss.hierarchyname',
                                'hie.xss.hierarchyhidden',
                                'hie.xss.role',
                                'hie.xss.user',
                                'hie.xss.type',
                            )
                            ->equal('hie.xss.type', 'edit')
                            ->get();
                        PHP,
                    ['browse-700'],
                ),
            ],
            'browse-data/900-cost-centers' => [
                new XmlExample(
                    'Compose the browse request and send it',
                    $browse . '#compose-the-browse-request-and-send-it',
                    <<<'PHP'
                        $twinfield->browse(BrowseCode::CostCenters)
                            ->columns(
                                'fin.trs.line.dim2',
                                'fin.trs.line.dim2name',
                                'fin.trs.line.dim1',
                                'fin.trs.line.dim1name',
                                'fin.trs.head.code',
                                'fin.trs.head.number',
                                'fin.trs.line.basevaluesigned',
                            )
                            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
                            ->get();
                        PHP,
                    ['browse-900'],
                ),
            ],
            'browse-data/list-browse-fields' => [
                new XmlExample(
                    'Browse fields',
                    $browse . '#browse-fields',
                    '$twinfield->browseDefinitions()->fields();',
                    ['browse-fields'],
                    limit: 3,
                ),
            ],
            'browse-data/read-browse-definition' => [
                new XmlExample(
                    'Read the browse definition',
                    $browse . '#read-the-browse-definition',
                    "\$twinfield->browseDefinitions()->get('030_3');",
                    ['browse-definition-030_3'],
                    limit: 3,
                ),
            ],

            // Cash transactions.
            'cash-transactions/daily-cash-register' => [
                new XmlExample(
                    'Create',
                    $cash . '#createupdate',
                    <<<'PHP'
                        $twinfield->cashTransactions()->create(
                            CashTransactionInput::create('KAS', '1000', new DateTimeImmutable('2026-11-03'))
                                ->statement(311, startValue: '250.00')
                                ->description('Cash register 3 November')
                                ->receive('8000', '500.00', vatCode: 'VH', vatValue: '105.00', description: 'Sales 21% VAT')
                                ->receive('8010', '100.00', vatCode: 'VL', vatValue: '9.00', description: 'Sales 9% VAT')
                                ->pay('4300', '20.00', vatCode: 'IH', vatValue: '4.20', description: 'Coffee and milk'),
                        );
                        PHP,
                    ['cash-transaction-read'],
                    showResponse: false,
                    responseNote: $savedLikeRead('cash-transactions/read-cash-transaction'),
                ),
            ],
            'cash-transactions/read-cash-transaction' => [
                new XmlExample(
                    'Read',
                    $cash . '#read',
                    "\$twinfield->cashTransactions()->get('KAS', 202600046);",
                    ['cash-transaction-read'],
                ),
            ],

            // Deleted transactions.
            'deleted-transactions/list-deleted-transactions' => [
                new XmlExample(
                    'GetDeletedTransactions',
                    self::DOCS . 'transaction-data/deleted-transactions/#getdeletedtransactions',
                    '$twinfield->deletedTransactions()->list();',
                    ['deleted-transactions'],
                    limit: 2,
                ),
            ],

            // Electronic bank statements.
            'electronic-bank-statements/import-electronic-bank-statement' => [
                new XmlExample(
                    'Create',
                    $statements . '#create',
                    <<<'PHP'
                        $twinfield->electronicBankStatements()->create(
                            ElectronicBankStatementInput::forIban('NL91ABNA0417164300')
                                ->date(new DateTimeImmutable('2026-11-09'))
                                ->currency('EUR')
                                ->number(2, startValue: '7.68', closeValue: '151.18')
                                ->receive('151.00', 'Invoice 2026-0042', type: 'N100', contraAccount: '1234567890', contraName: 'Example customer')
                                ->pay('7.50', 'Bank costs', type: 'N999', contraIban: 'NL20INGB0001234567'),
                        );
                        PHP,
                    ['electronic-bank-statement-create'],
                ),
            ],
            'electronic-bank-statements/list-electronic-bank-statements' => [
                new XmlExample(
                    'GetBankStatements',
                    self::DOCS . 'request-data/electronic-bank-statements/#getbankstatements',
                    <<<'PHP'
                        $twinfield->electronicBankStatements()->list(
                            from: new DateTimeImmutable('2026-11-05'),
                            to: new DateTimeImmutable('2026-11-05'),
                        );
                        PHP,
                    ['electronic-bank-statements-list'],
                    limit: 1,
                ),
            ],

            // Journal transactions.
            'journal-transactions/create-journal-transaction' => [
                new XmlExample(
                    'Create',
                    $journal . '#createupdate',
                    <<<'PHP'
                        $twinfield->journalTransactions()->create(
                            JournalTransactionInput::create('MEMO', new DateTimeImmutable('2026-11-04'))
                                ->debit('4008', '435.55')
                                ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid'),
                        );
                        PHP,
                    ['journal-transaction-save'],
                ),
            ],
            'journal-transactions/opening-balance' => [
                new XmlExample(
                    'Create',
                    $journal . '#createupdate',
                    <<<'PHP'
                        $twinfield->journalTransactions()->create(
                            JournalTransactionInput::create('BEGINBALANS', new DateTimeImmutable('2026-01-01'))
                                ->period(Period::of(2026, 0))
                                ->debit('1010', '2500.00', description: 'Opening balance')
                                ->credit('0500', '2500.00', description: 'Opening balance'),
                        );
                        PHP,
                    ['journal-transaction-save'],
                    showResponse: false,
                    responseNote: $savedLikeRead('journal-transactions/create-journal-transaction'),
                ),
            ],
            'journal-transactions/read-journal-transaction' => [
                new XmlExample(
                    'Read',
                    $journal . '#read',
                    "\$twinfield->journalTransactions()->get('MEMO', 202600003);",
                    ['journal-transaction-read'],
                ),
            ],

            // Purchase transactions.
            'purchase-transactions/create-purchase-transaction' => [
                new XmlExample(
                    'Create',
                    $purchase . '#createupdate',
                    <<<'PHP'
                        $twinfield->purchaseTransactions()->create(
                            PurchaseTransactionInput::invoice('INK', '2000', 'F-2026-0044', new DateTimeImmutable('2026-11-09'))
                                ->dueDate(new DateTimeImmutable('2026-12-09'))
                                ->paymentReference('F-2026-0044')
                                ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00', description: 'Consultancy'),
                        );
                        PHP,
                    ['purchase-transaction-save'],
                ),
            ],
            'purchase-transactions/credit-note' => [
                new XmlExample(
                    'Create',
                    $purchase . '#createupdate',
                    <<<'PHP'
                        $twinfield->purchaseTransactions()->create(
                            PurchaseTransactionInput::creditNote('INK', '2000', 'F-2026-0050', new DateTimeImmutable('2026-11-09'))
                                ->dueDate(new DateTimeImmutable('2026-11-09'))
                                ->description('Credit note for F-2026-0044')
                                ->expense('7000', '40.00', vatCode: 'IH', vatValue: '8.40', description: 'Returned goods'),
                        );
                        PHP,
                    ['purchase-transaction-save'],
                    showResponse: false,
                    responseNote: $savedLikeRead('purchase-transactions/create-purchase-transaction'),
                ),
            ],
            'purchase-transactions/open-invoices' => [
                new XmlExample(
                    'Find',
                    $purchase . '#find',
                    '$twinfield->purchaseTransactions()->openInvoices()->limit(20)->get();',
                    ['finder-open-invoices'],
                ),
            ],
            'purchase-transactions/read-purchase-transaction' => [
                new XmlExample(
                    'Read',
                    $purchase . '#read',
                    "\$twinfield->purchaseTransactions()->get('INK', 202600012);",
                    ['purchase-transaction-read'],
                ),
            ],

            // Reversal transactions.
            'reversal-transactions/read-reversal' => [
                new XmlExample(
                    'Get',
                    $reversal . '#get',
                    "\$twinfield->reversalTransactions()->get('MEMO', 202600003);",
                    ['reversal-transaction-get-reversed'],
                ),
            ],
            'reversal-transactions/reverse-journal-transaction' => [
                new XmlExample(
                    'Get',
                    $reversal . '#get',
                    "\$twinfield->reversalTransactions()->get('MEMO', 202600003);",
                    ['reversal-transaction-get'],
                ),
                new XmlExample(
                    'Post final',
                    $reversal . '#post-final',
                    "\$twinfield->reversalTransactions()->postFinal('MEMO', 202600003);",
                    ['reversal-transaction-post-final'],
                ),
            ],

            // Sales invoices.
            'sales-invoices/create-sales-invoice' => [
                new XmlExample(
                    'Calculate',
                    $invoices . '#createupdate',
                    <<<'PHP'
                        $twinfield->salesInvoices()->calculate(
                            SalesInvoiceInput::create('FACTUUR', '1000')->article('510', '2', '501.01'),
                        );
                        PHP,
                    ['sales-invoice-calculate'],
                ),
                new XmlExample(
                    'Create',
                    $invoices . '#createupdate',
                    <<<'PHP'
                        $twinfield->salesInvoices()->create(
                            SalesInvoiceInput::create('FACTUUR', '1000')
                                ->paymentMethod(PaymentMethod::Bank)
                                ->article('510', quantity: '2', subarticle: '501.01'),
                        );
                        PHP,
                    ['sales-invoice-save'],
                ),
            ],
            'sales-invoices/finalize-sales-invoice' => [
                new XmlExample(
                    'Make final',
                    $invoices . '#createupdate',
                    "\$twinfield->salesInvoices()->finalize('FACTUUR', 12);",
                    ['sales-invoice-read-concept', 'sales-invoice-save-final'],
                    responseNote: '`finalize()` reads the concept invoice first, and sends it again with status `final`.',
                ),
            ],
            'sales-invoices/list-invoice-types' => [
                new XmlExample(
                    'Find invoice types',
                    self::DOCS . 'miscellaneous/finder/#finder-type',
                    '$twinfield->salesInvoices()->invoiceTypes()->get();',
                    ['finder-invoice-types'],
                ),
            ],
            'sales-invoices/read-sales-invoice' => [
                new XmlExample(
                    'Read',
                    $invoices . '#read',
                    "\$twinfield->salesInvoices()->get('FACTUUR', 12);",
                    ['sales-invoice-read-concept'],
                ),
            ],

            // Sales transactions.
            'sales-transactions/create-sales-transaction' => [
                new XmlExample(
                    'Create',
                    $sales . '#createupdate',
                    <<<'PHP'
                        $twinfield->salesTransactions()->create(
                            SalesTransactionInput::invoice('VRK', '1000', '2026-0044', new DateTimeImmutable('2026-11-09'))
                                ->dueDate(new DateTimeImmutable('2026-12-09'))
                                ->paymentReference('2026-0044')
                                ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00', description: 'Consultancy'),
                        );
                        PHP,
                    ['sales-transaction-save'],
                ),
            ],
            'sales-transactions/credit-note' => [
                new XmlExample(
                    'Create',
                    $sales . '#createupdate',
                    <<<'PHP'
                        $twinfield->salesTransactions()->create(
                            SalesTransactionInput::creditNote('VRK', '1000', '2026-0050', new DateTimeImmutable('2026-11-09'))
                                ->dueDate(new DateTimeImmutable('2026-11-09'))
                                ->description('Credit note for 2026-0044')
                                ->revenue('8000', '40.00', vatCode: 'VH', vatValue: '8.40', description: 'Returned goods'),
                        );
                        PHP,
                    ['sales-transaction-save'],
                    showResponse: false,
                    responseNote: $savedLikeRead('sales-transactions/create-sales-transaction'),
                ),
            ],
            'sales-transactions/open-invoices' => [
                new XmlExample(
                    'Find',
                    $sales . '#find',
                    '$twinfield->salesTransactions()->openInvoices()->limit(20)->get();',
                    ['finder-open-invoices'],
                ),
            ],
            'sales-transactions/read-sales-transaction' => [
                new XmlExample(
                    'Read',
                    $sales . '#read',
                    "\$twinfield->salesTransactions()->get('VRK', 202600012);",
                    ['sales-transaction-read'],
                ),
            ],

            // Transaction blocked value.
            'transaction-blocked-values/register-blocked-value' => [
                new XmlExample(
                    'RegisterBlockedAmountForTransaction',
                    $blocked . '#registerblockedamountfortransaction',
                    "\$twinfield->transactionBlockedValues()->register('MEMO', 202100001, 1, '2000.00');",
                    ['transaction-blocked-value-process'],
                ),
            ],
            'transaction-blocked-values/unregister-blocked-value' => [
                new XmlExample(
                    'UnregisterBlockedAmountForTransaction',
                    $blocked . '#unregisterblockedamountfortransaction',
                    "\$twinfield->transactionBlockedValues()->unregister('MEMO', 202100001, 1);",
                    ['transaction-blocked-value-process'],
                ),
            ],
        ];
    }
}
