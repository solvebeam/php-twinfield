<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Credit management settings of the office (`creditmanagement`): when open invoices show up in
 * the credit management lists, and how those lists are built.
 */
final readonly class CreditManagement
{
    /**
     * @param array<int, string> $prompts Browse fields by position (`prompt/@id`); empty positions are left out.
     * @param list<SortField> $sort Sort fields in order (`field/@id`); empty positions are left out.
     */
    public function __construct(
        /** Days after the due date before an invoice shows up (`daysafterduedate`). */
        public int $daysAfterDueDate,
        /** Minimum open amount for that (`daysafterduedatevalue`). */
        public Amount $daysAfterDueDateAmount,
        /** Days after a part payment before the invoice shows up again (`daysafterpartpayment`). */
        public int $daysAfterPartPayment,
        /** Browse for the call list (`invoicebrowse`), e.g. `160`. */
        public NamedReference $invoiceBrowse,
        /** Browse with the details per invoice (`invoiceexplodebrowse`), e.g. `161`. */
        public NamedReference $invoiceExplodeBrowse,
        public array $prompts,
        public array $sort,
    ) {
    }

    public static function fromXml(XmlElement $creditManagement): self
    {
        $prompts = [];

        foreach ($creditManagement->child('prompts')->list('prompt') as $prompt) {
            $id = $prompt->intAttribute('id');

            if (array_key_exists($id, $prompts)) {
                throw new UnexpectedResponseException(sprintf('%s/@id %d is not unique.', $prompt->path, $id));
            }

            // An empty prompt is an unused position.
            if ($prompt->text() !== '') {
                $prompts[$id] = $prompt->text();
            }
        }

        ksort($prompts);

        $sort = [];

        foreach ($creditManagement->child('sort')->list('field') as $field) {
            $sortField = SortField::nullable($field);

            if ($sortField !== null) {
                $sort[$field->intAttribute('id')] = $sortField;
            }
        }

        ksort($sort);

        return new self(
            daysAfterDueDate: $creditManagement->int('daysafterduedate'),
            daysAfterDueDateAmount: TransactionXml::amount($creditManagement, 'daysafterduedatevalue'),
            daysAfterPartPayment: $creditManagement->int('daysafterpartpayment'),
            invoiceBrowse: NamedReference::fromXml($creditManagement->child('invoicebrowse')),
            invoiceExplodeBrowse: NamedReference::fromXml($creditManagement->child('invoiceexplodebrowse')),
            prompts: $prompts,
            sort: array_values($sort),
        );
    }
}
