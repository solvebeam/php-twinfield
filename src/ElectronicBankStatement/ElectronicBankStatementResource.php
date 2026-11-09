<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use DOMElement;
use DOMNode;
use SolveBeam\Twinfield\ProcessXml\MessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
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
 * @see https://developers.twinfield.com/documentation/api/transaction-data/electronic-bank-statements/
 */
final readonly class ElectronicBankStatementResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
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
}
