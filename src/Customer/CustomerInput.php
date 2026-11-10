<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\PaymentCondition;
use SolveBeam\Twinfield\Dimension\PostingRuleInput;
use SolveBeam\Twinfield\Dimension\RelationInput;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A customer to create or update. Immutable: every method returns a new input.
 *
 *   $input = CustomerInput::create('Example B.V.')
 *       ->website('www.example.com')
 *       ->dueDays(30)
 *       ->vatCode('VH')
 *       ->eInvoicing('invoices@example.com')
 *       ->directDebit('SEPANLDD')
 *       ->collectMandate('MANDATE-001', new DateTimeImmutable('2026-01-15'))
 *       ->collectionSchema(CollectionSchema::Core)
 *       ->creditLimit('5000.00')
 *       ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
 *       ->address(
 *           AddressInput::invoice('Example B.V.')
 *               ->field(2, 'Main street 1')
 *               ->postcode('1234 AB')
 *               ->city('Amsterdam')
 *               ->country('NL')
 *               ->vatNumber('NL001234567B01')
 *               ->default(),
 *       )
 *       ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('Example B.V.')->default())
 *       ->paymentCondition(8, '2');
 *
 * Without code() Twinfield gives the customer the next free code of the DEB dimension type.
 *
 * Only what you set is sent: anything else gets the default of the dimension type when
 * creating, and stays as it is when updating. Setting a value to null clears it. For an
 * existing customer, start from `$customer->edit()`, which sets every value.
 *
 * Lists (addresses, bank accounts, groups, payment conditions) are sent as a whole once you
 * set them: an address you leave out is removed. Posting rules you remove are sent with
 * status `deleted`.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/customers/
 */
final readonly class CustomerInput
{
    use RelationInput;

    /** For messages. */
    private const RELATION = 'customer';

    /**
     * Elements in the order of the documentation; `a/b` is element `b` in `a`.
     */
    private const ELEMENTS = [
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
        'financials/ebilling',
        'financials/ebillmail',
        'financials/substitutewith',
        'financials/vatcode',
        'financials/collectmandate/id',
        'financials/collectmandate/signaturedate',
        'financials/collectionschema',
        'creditmanagement/responsibleuser',
        'creditmanagement/basecreditlimit',
        'creditmanagement/sendreminder',
        'creditmanagement/reminderemail',
        'creditmanagement/blocked',
        'creditmanagement/freetext1',
        'creditmanagement/freetext2',
        'creditmanagement/comment',
        'remittanceadvice/sendtype',
        'remittanceadvice/sendmail',
        'invoicing/discountarticle',
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
     * Collects invoices by direct debit, with a payment file.
     *
     * @param ?string $payCode Payment type code, e.g. `SEPANLDD`.
     */
    public function directDebit(?string $payCode): self
    {
        return $this->setMany([
            'financials/payavailable' => 'true',
            'financials/meansofpayment' => MeansOfPayment::PaymentFile->value,
            'financials/paycode' => self::nullableCode($payCode),
        ]);
    }

    /**
     * No direct debit: the customer pays the invoices.
     */
    public function withoutDirectDebit(): self
    {
        return $this->setMany([
            'financials/payavailable' => 'false',
            'financials/meansofpayment' => MeansOfPayment::None->value,
            'financials/paycode' => null,
        ]);
    }

    /**
     * SEPA mandate to collect with: its ID (at most 35 characters) and signature date.
     */
    public function collectMandate(string $id, ?DateTimeInterface $signatureDate = null): self
    {
        if ($id === '') {
            throw new InvalidArgumentException('Mandate id must not be empty.');
        }

        XmlValue::maxLength('mandate id', $id, 35);

        return $this->setMany([
            'financials/collectmandate/id' => $id,
            'financials/collectmandate/signaturedate' => $signatureDate === null
                ? null
                : XmlValue::date($signatureDate),
        ]);
    }

    /**
     * SEPA scheme for direct debit.
     */
    public function collectionSchema(CollectionSchema $schema): self
    {
        return $this->set('financials/collectionschema', $schema->value);
    }

    /**
     * Sends invoices by email, to this address (at most 200 characters) when given.
     */
    public function eInvoicing(?string $email = null): self
    {
        $input = $this->set('financials/ebilling', 'true');

        return $email === null ? $input : $input->eInvoicingEmail($email);
    }

    /**
     * Sends invoices by post. The email address is kept.
     */
    public function withoutEInvoicing(): self
    {
        return $this->set('financials/ebilling', 'false');
    }

    /**
     * Email address for invoices, at most 200 characters. Not checked as one EmailAddress:
     * Twinfield keeps several addresses here, separated by a comma.
     */
    public function eInvoicingEmail(?string $email): self
    {
        return $this->set('financials/ebillmail', self::maxLength('invoice email', $email, 200));
    }

    /**
     * Credit manager (user code).
     */
    public function responsibleUser(?string $user): self
    {
        return $this->set('creditmanagement/responsibleuser', self::maxLength('responsible user', $user, 16));
    }

    /**
     * Credit limit, e.g. `'5000.00'`; `'0.00'` for none.
     *
     * @param Amount|numeric-string $limit
     */
    public function creditLimit(Amount|string $limit): self
    {
        return $this->set('creditmanagement/basecreditlimit', Amount::of($limit)->value);
    }

    /**
     * Whether and how Twinfield sends payment reminders, and the email address for them.
     *
     *   ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
     *   ->sendReminder(SendReminder::No)
     *
     * @throws InvalidArgumentException For SendReminder::ByEmail without email address.
     */
    public function sendReminder(SendReminder $sendReminder, ?string $email = null): self
    {
        $email = $email === '' ? null : $email;

        if ($sendReminder === SendReminder::ByEmail && $email === null) {
            throw new InvalidArgumentException('Reminders by email need an email address.');
        }

        return $this->setMany([
            'creditmanagement/sendreminder' => $sendReminder->value,
            'creditmanagement/reminderemail' => self::maxLength('reminder email', $email, 200),
        ]);
    }

    /**
     * Blocks the projects of the customer in time & expenses.
     */
    public function blocked(bool $blocked = true): self
    {
        return $this->set('creditmanagement/blocked', XmlValue::boolean($blocked));
    }

    /**
     * Right of use (free text 1).
     */
    public function rightOfUse(?bool $rightOfUse): self
    {
        return $this->set('creditmanagement/freetext1', $rightOfUse === null ? null : XmlValue::boolean($rightOfUse));
    }

    /**
     * Segment code (free text 2), at most 40 characters.
     */
    public function segmentCode(?string $segmentCode): self
    {
        return $this->set('creditmanagement/freetext2', self::maxLength('segment code', $segmentCode, 40));
    }

    /**
     * Credit management comment.
     */
    public function comment(?string $comment): self
    {
        return $this->set('creditmanagement/comment', $comment === '' ? null : $comment);
    }

    /**
     * Article for discounts or premiums on invoices.
     */
    public function discountArticle(?string $article): self
    {
        return $this->set('invoicing/discountarticle', self::nullableCode($article));
    }

    /**
     * The `<dimension>` request to save this customer in a company.
     *
     * @internal
     */
    public function toXml(string $company): XmlBuilder
    {
        return $this->dimensionXml($company, 'DEB');
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
