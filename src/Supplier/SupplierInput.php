<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Supplier;

use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\RelationInput;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

/**
 * A supplier to create or update. Immutable: every method returns a new input.
 *
 *   $input = SupplierInput::create('Example Supplies B.V.')
 *       ->dueDays(30)
 *       ->balanceAccount('1600')
 *       ->vatCode('IH')
 *       ->relationsReference('CUST-4711')
 *       ->creditTransfer('SEPANLCT')
 *       ->remittanceAdvice(RemittanceAdviceSendType::ByEmail, 'accounts@example.com')
 *       ->address(
 *           AddressInput::invoice('Example Supplies B.V.')
 *               ->field(2, 'Main street 1')
 *               ->postcode('1234 AB')
 *               ->city('Amsterdam')
 *               ->country('NL')
 *               ->vatNumber('NL001234567B01')
 *               ->default(),
 *       )
 *       ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('Example Supplies B.V.')->default())
 *       ->paymentCondition(10, '2');
 *
 * Without code() Twinfield gives the supplier the next free code of the CRD dimension type.
 *
 * Only what you set is sent: anything else gets the default of the dimension type when
 * creating, and stays as it is when updating. Setting a value to null clears it. For an
 * existing supplier, start from `$supplier->edit()`, which sets every value.
 *
 * Lists (addresses, bank accounts, groups, payment conditions) are sent as a whole once you
 * set them: an address you leave out is removed. Posting rules you remove are sent with
 * status `deleted`.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/suppliers/
 */
final readonly class SupplierInput
{
    use RelationInput;

    /** For messages. */
    private const string RELATION = 'supplier';

    /**
     * Elements in the order of the documentation; `a/b` is element `b` in `a`.
     */
    private const array ELEMENTS = [
        'shortname',
        'beginperiod',
        'beginyear',
        'endperiod',
        'endyear',
        'website',
        'financials/duedays',
        'financials/payavailable',
        'financials/meansofpayment',
        'financials/paycode',
        'financials/substitutewith',
        'financials/relationsreference',
        'financials/vatcode',
        'remittanceadvice/sendtype',
        'remittanceadvice/sendmail',
    ];

    /**
     * @param array<string, ?string> $values Element (see ELEMENTS) => value; null sends an
     *     empty element, which clears the value.
     * @param ?list<AddressInput> $addresses Null when not set: the addresses are not sent.
     * @param ?list<BankInput> $banks Null when not set.
     * @param ?list<string> $groups Null when not set.
     * @param ?list<PostingRuleInput> $postingRules Null when not set.
     * @param ?list<PaymentCondition> $paymentConditions Null when not set.
     */
    private function __construct(
        /** Code; null to let Twinfield give the next free code. */
        public ?string $code,
        public string $name,
        private array $values,
        public ?array $addresses,
        public ?array $banks,
        public ?array $groups,
        public ?array $postingRules,
        public ?array $paymentConditions,
    ) {
        self::validate($code, $name, $addresses, $banks);
    }

    /**
     * @param string $name Name, at most 80 characters.
     */
    public static function create(string $name): self
    {
        return new self(null, $name, [], null, null, null, null, null);
    }

    /**
     * Twinfield pays the supplier by credit transfer, with a payment file.
     *
     * @param ?string $payCode Payment type code, e.g. `SEPANLCT`.
     */
    public function creditTransfer(?string $payCode): self
    {
        return $this->setMany([
            'financials/payavailable' => 'true',
            'financials/meansofpayment' => MeansOfPayment::PaymentFile->value,
            'financials/paycode' => self::nullableCode($payCode),
        ]);
    }

    /**
     * Twinfield pays the supplier by cheque.
     */
    public function cheques(): self
    {
        return $this->setMany([
            'financials/payavailable' => 'true',
            'financials/meansofpayment' => MeansOfPayment::Cheques->value,
            'financials/paycode' => null,
        ]);
    }

    /**
     * Twinfield does not pay the supplier: you pay outside Twinfield.
     */
    public function withoutPayments(): self
    {
        return $this->setMany([
            'financials/payavailable' => 'false',
            'financials/meansofpayment' => MeansOfPayment::None->value,
            'financials/paycode' => null,
        ]);
    }

    /**
     * External supplier number, at most 40 characters, e.g. your customer number at the supplier.
     */
    public function relationsReference(?string $reference): self
    {
        return $this->set('financials/relationsreference', self::maxLength('relations reference', $reference, 40));
    }

    /**
     * The `<dimension>` request to save this supplier in a company.
     *
     * @internal
     */
    public function toXml(string $company): XmlBuilder
    {
        return $this->dimensionXml($company, 'CRD');
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
