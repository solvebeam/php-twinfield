<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use Closure;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\MessageType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlMessage;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Read, save, post and delete transactions on the XML web services; the resources of each
 * kind of transaction (bank, cash, …) use it with their own model.
 *
 * @internal
 *
 * @template T of object
 */
final readonly class TransactionClient
{
    /**
     * @param Closure(XmlElement): T $map Maps a `<transaction>` element of a response to the model.
     * @param string $kind For messages, e.g. `Bank transactions`.
     */
    public function __construct(
        private Twinfield $twinfield,
        private Closure $map,
        private string $kind,
    ) {
    }

    /**
     * @return T
     *
     * @throws TransactionNotFoundException
     */
    public function get(string $dayBook, int $number): object
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'transaction');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', XmlValue::code($dayBook));
        $xml->add($xml->root(), 'number', (string) $number);

        try {
            $result = $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw TransactionNotFoundException::fromProcessXml($exception, $dayBook, $number) ?? $exception;
        }

        return $this->mapRoot($result);
    }

    /**
     * @return ?T
     */
    public function find(string $dayBook, int $number): ?object
    {
        try {
            return $this->get($dayBook, $number);
        } catch (TransactionNotFoundException) {
            return null;
        }
    }

    /**
     * @return T
     */
    public function create(TransactionInput $input): object
    {
        if ($input->transactionNumber() !== null) {
            throw new InvalidArgumentException(sprintf(
                'A new transaction has no number, %d given: use update() to change transaction %s %d.',
                $input->transactionNumber(),
                $input->dayBook(),
                $input->transactionNumber(),
            ));
        }

        return $this->save($input);
    }

    /**
     * @return T
     *
     * @throws TransactionNotFoundException
     */
    public function update(TransactionInput $input): object
    {
        $number = $input->transactionNumber() ?? throw new InvalidArgumentException(sprintf(
            'Updating needs the transaction number: start from $transaction->edit(), '
                . 'or use create() for a new transaction in %s.',
            $input->dayBook(),
        ));

        $this->get($input->dayBook(), $number);

        return $this->save($input);
    }

    /**
     * @return T
     */
    public function save(TransactionInput $input): object
    {
        $result = $this->twinfield->processXml($input->toXml($this->company())->toXml());

        return $this->mapRoot($result);
    }

    /**
     * @return SaveManyResult<T>
     */
    public function saveMany(TransactionInput ...$inputs): SaveManyResult
    {
        $inputs = array_values($inputs);

        /** @var list<SavedTransaction<T>|FailedTransaction> $results */
        $results = [];

        if ($inputs === []) {
            return new SaveManyResult($results);
        }

        $company = $this->company();
        $xml = XmlBuilder::create('transactions');

        foreach ($inputs as $input) {
            $input->appendTo($xml, $xml->root(), $company);
        }

        try {
            $result = $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            // Some transactions may have been saved; the result says which.
            $result = $exception->result;
        }

        $root = XmlElement::root($result);

        if ($root->element->localName !== 'transactions') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <transactions> response, got <%s>.',
                $root->element->localName,
            ));
        }

        $elements = $root->list('transaction');

        if (count($elements) !== count($inputs)) {
            throw new UnexpectedResponseException(sprintf(
                'Sent %d transactions, got %d back.',
                count($inputs),
                count($elements),
            ));
        }

        $messages = $result->messages();

        foreach ($elements as $index => $element) {
            $own = array_values(array_filter(
                $messages,
                static fn (ProcessXmlMessage $message): bool => self::contains($element->element, $message->element),
            ));
            $errors = self::ofType($own, MessageType::Error);
            $warnings = self::ofType($own, MessageType::Warning);

            $results[] = match ($element->attribute('result')) {
                '1' => new SavedTransaction($index, $inputs[$index], ($this->map)($element), $warnings),
                '0' => new FailedTransaction($index, $inputs[$index], $errors, $warnings),
                default => throw new UnexpectedResponseException(sprintf(
                    '%s/@result is "%s", expected 1 or 0.',
                    $element->path,
                    $element->attribute('result'),
                )),
            };
        }

        return new SaveManyResult($results);
    }

    /**
     * Posts a provisional transaction: it becomes final.
     */
    public function post(string $dayBook, int $number): void
    {
        $this->action('post', $dayBook, $number, []);
    }

    /**
     * Deletes a provisional transaction.
     */
    public function delete(string $dayBook, int $number, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Deleting a transaction needs a reason.');
        }

        $this->action('delete', $dayBook, $number, ['reason' => $reason]);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function action(string $action, string $dayBook, int $number, array $attributes): void
    {
        $xml = XmlBuilder::create('transaction', ['action' => $action, ...$attributes]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', XmlValue::code($dayBook));
        $xml->add($xml->root(), 'number', (string) $number);

        $this->twinfield->processXml($xml->toXml());
    }

    /**
     * @return T
     */
    private function mapRoot(ProcessXmlResult $result): object
    {
        $root = XmlElement::root($result);

        if ($root->element->localName !== 'transaction') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <transaction> response, got <%s>.',
                $root->element->localName,
            ));
        }

        return ($this->map)($root);
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException(sprintf('%s belong to a company: use company() first.', $this->kind));
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
