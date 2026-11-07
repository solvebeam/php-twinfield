<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * Header values every transaction has, as Twinfield returns them.
 *
 * Optional values are null when Twinfield leaves them out of the response.
 */
final readonly class TransactionHeader
{
    public function __construct(
        /** Company (office) code. */
        public string $company,
        /** Day book (transaction type) code, e.g. `BNK`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600045`. */
        public int $number,
        public TransactionStatus $status,
        public FinancialPeriod $period,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        public DateTimeImmutable $date,
        /** Where the transaction comes from, e.g. `import`. */
        public ?string $origin,
        /** Code of the user who created the transaction. */
        public ?string $user,
        public ?DateTimeImmutable $inputDate,
        public ?DateTimeImmutable $modificationDate,
        public ?string $freeText1,
        public ?string $freeText2,
        public ?string $freeText3,
    ) {
        foreach (['company' => $company, 'day book' => $dayBook, 'currency' => $currency] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Transaction %s must not be empty.', $field));
            }
        }

        if ($number < 1) {
            throw new InvalidArgumentException('Transaction number must be 1 or more.');
        }
    }

    /**
     * From a `<transaction>` element of a read or save response.
     */
    public static function fromXml(XmlElement $transaction): self
    {
        $header = $transaction->child('header');

        return new self(
            company: $header->string('office'),
            dayBook: $header->string('code'),
            number: $header->int('number'),
            status: $transaction->enumAttribute('location', TransactionStatus::class),
            period: TransactionXml::period($header, 'period'),
            currency: $header->string('currency'),
            date: TransactionXml::date($header, 'date'),
            origin: TransactionXml::optionalString($header, 'origin'),
            user: TransactionXml::optionalString($header, 'user'),
            inputDate: TransactionXml::optionalDateTime($header, 'inputdate'),
            modificationDate: TransactionXml::optionalDateTime($header, 'modificationdate'),
            freeText1: TransactionXml::optionalString($header, 'freetext1'),
            freeText2: TransactionXml::optionalString($header, 'freetext2'),
            freeText3: TransactionXml::optionalString($header, 'freetext3'),
        );
    }

    public function isProvisional(): bool
    {
        return $this->status === TransactionStatus::Provisional;
    }
}
