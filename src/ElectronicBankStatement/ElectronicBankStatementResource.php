<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\ProcessXml\MessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Electronic bank statements of the company: import them as the bank reports them, and let
 * Twinfield allocate the lines with its allocation rules.
 *
 *   $statements = $twinfield->company('1000')->electronicBankStatements();
 *
 *   $statement = $statements->create(
 *       ElectronicBankStatementInput::forIban('NL91ABNA0417164300')
 *           ->date(new DateTimeImmutable('2026-11-09'))
 *           ->receive('151.00', 'Invoice 2026-0042', contraIban: 'NL20INGB0001234567'),
 *   );
 *
 *   foreach ($statement->lines as $line) {
 *       echo $line->description, ': ', $line->dimension(1)?->code, PHP_EOL;
 *   }
 *
 * The difference with `bankTransactions()`: there you choose the ledger accounts yourself; here
 * you send the bank lines and Twinfield books them, like a statement downloaded from the bank.
 *
 * A statement can't be changed after it is created, so there is no update or delete. Twinfield
 * refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * Read statements, also the ones Twinfield downloaded from the bank itself, with list() (by
 * statement date) or listByCreationDate(), through the BankStatementService:
 *
 *   foreach ($statements->list(new DateTimeImmutable('-7 days'), new DateTimeImmutable()) as $statement) {
 *       echo $statement->bankBook, ' ', $statement->number, ': ', count($statement->lines), ' lines', PHP_EOL;
 *   }
 *
 * A statement is not a bank transaction: once posted, its transaction number is in
 * BankStatement::$transactionNumber. Read bank transactions with `bankTransactions()` or browse data.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/electronic-bank-statements/
 * @see https://developers.twinfield.com/documentation/api/request-data/electronic-bank-statements/
 */
final readonly class ElectronicBankStatementResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Statements dated from `$from` to `$to` (both days included), with their lines and allocations.
     *
     *   $statements = $twinfield->electronicBankStatements()->list(
     *       from: new DateTimeImmutable('2026-11-01'),
     *       to: new DateTimeImmutable('2026-11-30'),
     *   );
     *
     * Twinfield compares the date and time literally and statements are dated at midnight, so
     * only the date of `$from` and `$to` is sent. Twinfield has no paging: a long period with
     * many statements can time out, so ask for a month or less at a time.
     *
     * @param bool $includePosted Also give statements that are already posted. Twinfield's own
     *                            default is `false`; this SDK includes them unless you say otherwise,
     *                            since automatically processed statements are posted right away.
     *
     * @return list<BankStatement> In the order Twinfield gives them.
     *
     * @throws CommandQueryFaultException With a BankStatementServiceFaultCode as `errorCode`.
     */
    public function list(DateTimeInterface $from, DateTimeInterface $to, bool $includePosted = true): array
    {
        $from = DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $to = DateTimeImmutable::createFromInterface($to)->setTime(0, 0);

        self::checkPeriod($from, $to, 'Y-m-d');

        // Field order follows the WSDL.
        return $this->query('GetBankStatements', [
            'IncludePostedStatements' => $includePosted,
            'StatementDateFrom' => $from,
            'StatementDateTo' => $to,
        ]);
    }

    /**
     * Statements created (imported into Twinfield) from `$from` to `$to`, e.g. to pick up new
     * statements since the last run. Leave out `$from` or `$to` for an open period; without
     * `$from` you get every statement up to `$to`, which can time out.
     *
     *   $statements = $twinfield->electronicBankStatements()->listByCreationDate(
     *       from: new DateTimeImmutable('yesterday'),
     *   );
     *
     * The date and time are sent as given.
     *
     * @param bool $includePosted Also give statements that are already posted; see list().
     *
     * @return list<BankStatement> In the order Twinfield gives them, which is not by date.
     *
     * @throws CommandQueryFaultException With a BankStatementServiceFaultCode as `errorCode`.
     */
    public function listByCreationDate(
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        bool $includePosted = true,
    ): array {
        if ($from === null && $to === null) {
            throw new InvalidArgumentException('Give a from date, a to date or both.');
        }

        if ($from !== null && $to !== null) {
            self::checkPeriod($from, $to, 'Y-m-d H:i:s');
        }

        // Field order follows the WSDL.
        return $this->query('GetBankStatementsByCreationDate', [
            'CreationDateFrom' => $from,
            'CreationDateTo' => $to,
            'IncludePostedStatements' => $includePosted,
        ]);
    }

    /**
     * Imports one statement.
     *
     * @param bool $importDuplicate Also import a statement Twinfield already has (`importduplicate="1"`).
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown IBAN.
     */
    public function create(ElectronicBankStatementInput $input, bool $importDuplicate = false): ElectronicBankStatement
    {
        $request = $input->toXml($this->twinfield->companyReference()->code, $importDuplicate)->toXml();
        $statements = $this->statements($this->twinfield->processXml($request), 1);

        return ElectronicBankStatement::fromXml($statements[0], $this->twinfield->companyReference());
    }

    /**
     * Imports several statements in one request. Twinfield imports the valid ones, so this does
     * not throw when some fail: check the result.
     *
     * @param list<ElectronicBankStatementInput> $inputs
     * @param bool $importDuplicate Also import statements Twinfield already has (`importduplicate="1"`).
     */
    public function createMany(array $inputs, bool $importDuplicate = false): CreateManyResult
    {
        if ($inputs === []) {
            return new CreateManyResult([]);
        }

        $request = $this->request($inputs, $importDuplicate);

        try {
            $result = $this->twinfield->processXml($request);
        } catch (ProcessXmlException $exception) {
            // Some statements may have been imported; the result says which.
            $result = $exception->result;
        }

        $company = $this->twinfield->companyReference();
        $messages = $result->messages();
        $results = [];

        foreach ($this->statements($result, count($inputs)) as $index => $statement) {
            $own = array_values(array_filter(
                $messages,
                static fn (ProcessXmlMessage $message): bool => self::contains($statement->element, $message->element),
            ));
            $errors = self::ofType($own, MessageType::Error);
            $warnings = self::ofType($own, MessageType::Warning);

            $results[] = match ($statement->attribute('result')) {
                '1' => new CreatedStatement(
                    $index,
                    $inputs[$index],
                    ElectronicBankStatement::fromXml($statement, $company),
                    $warnings,
                ),
                '0' => new FailedStatement($index, $inputs[$index], $errors, $warnings),
                default => throw new UnexpectedResponseException(sprintf(
                    '%s/@result is "%s", expected 1 or 0.',
                    $statement->path,
                    $statement->attribute('result'),
                )),
            };
        }

        return new CreateManyResult($results);
    }

    /**
     * @param list<ElectronicBankStatementInput> $inputs
     */
    private function request(array $inputs, bool $importDuplicate): string
    {
        $company = $this->twinfield->companyReference()->code;
        $xml = XmlBuilder::create('statements', ['importduplicate' => $importDuplicate ? '1' : '0']);

        foreach ($inputs as $input) {
            $input->appendTo($xml, $xml->root(), $company);
        }

        return $xml->toXml();
    }

    /**
     * @return list<XmlElement>
     */
    private function statements(ProcessXmlResult $result, int $expected): array
    {
        $root = XmlElement::root($result);

        if ($root->element->localName !== 'statements') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <statements> response, got <%s>.',
                $root->element->localName,
            ));
        }

        $statements = $root->list('statement');

        if (count($statements) !== $expected) {
            throw new UnexpectedResponseException(sprintf(
                'Sent %d bank statements, got %d back.',
                $expected,
                count($statements),
            ));
        }

        return $statements;
    }

    private static function contains(DOMElement $ancestor, DOMNode $node): bool
    {
        for ($current = $node; $current !== null; $current = $current->parentNode) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ProcessXmlMessage> $messages
     *
     * @return list<ProcessXmlMessage>
     */
    private static function ofType(array $messages, MessageType $type): array
    {
        return array_values(array_filter(
            $messages,
            static fn (ProcessXmlMessage $message): bool => $message->type === $type,
        ));
    }

    /**
     * @param array<string, bool|DateTimeInterface|null> $fields
     *
     * @return list<BankStatement>
     */
    private function query(string $type, array $fields): array
    {
        if ($this->twinfield->companyCode() === null) {
            throw new LogicException('Command & Query web services need a company: use company() first.');
        }

        $result = $this->twinfield->query('BankStatementService', new Query($type, $fields), 'GetBankStatementsResult');
        $company = $this->twinfield->companyReference();

        return array_map(
            static fn (ResultElement $statement): BankStatement => BankStatement::fromResult($statement, $company),
            $result->list('BankStatements', 'BankStatement'),
        );
    }

    /**
     * Twinfield gives an empty list when from is after to; refuse it so a mistake does not look
     * like a period without statements.
     */
    private static function checkPeriod(DateTimeInterface $from, DateTimeInterface $to, string $format): void
    {
        if ($from > $to) {
            throw new InvalidArgumentException(sprintf(
                'From date %s is after to date %s.',
                $from->format($format),
                $to->format($format),
            ));
        }
    }
}
