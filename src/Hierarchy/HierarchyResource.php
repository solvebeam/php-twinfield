<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Hierarchy;

use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Reporting structures (rapportagestructuren, hierarchies.asmx): search, read, save, bind and
 * unbind general ledger accounts, hide and delete.
 *
 *   $hierarchies = $twinfield->company('1000')->hierarchies();
 *
 *   foreach ($hierarchies->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $hierarchy = $hierarchies->get('RGS');
 *
 *   foreach (new RecursiveTreeIterator($hierarchy) as $line) {
 *       echo $line, PHP_EOL;
 *   }
 *
 * Reporting structures belong to the organisation: every company has the same nodes. The
 * accounts bound to the nodes differ per company: get(), bind() and unbind() work on the
 * accounts of the company of the client, which is required.
 *
 * Twinfield refusals are thrown as HierarchyException, an unknown code as
 * HierarchyNotFoundException.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/hierarchy/
 */
final readonly class HierarchyResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search reporting structures by code and name, e.g. `search('RGS*')->all()`.
     *
     * Finder type `HIE`. Every company of the organisation gives the same reporting structures.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<HierarchySummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $this->companyCode();

        $query = $this->twinfield->finder(FinderType::Hierarchies);

        return CodeNameSearch::forOrganisation($query, HierarchySummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * Search the nodes of a reporting structure by code and name, e.g.
     * `searchNodes('RGS', 'BIVA*')->all()`. The root node is included. The results are not in tree
     * order (nor by code or ID), and have no node ID and no accounts: use get() for those.
     *
     * Finder type `HND` with option `hierarchy`. An unknown reporting structure gives no results.
     *
     * @return CodeNameSearch<HierarchyNodeSummary>
     */
    public function searchNodes(string $hierarchyCode, string $pattern = '*'): CodeNameSearch
    {
        $this->companyCode();

        $query = $this->twinfield->finder(FinderType::HierarchyNodes)->option('hierarchy', $hierarchyCode);

        return CodeNameSearch::forOrganisation(
            $query,
            static fn (array $row, int $index, Organisation $organisation): HierarchyNodeSummary
                => HierarchyNodeSummary::fromFinderRow($row, $index, $organisation, $hierarchyCode),
        )->pattern($pattern);
    }

    /**
     * The reporting structure with all nodes, and the accounts of the company of the client.
     *
     * @throws HierarchyNotFoundException When there is no reporting structure with this code.
     * @throws HierarchyException
     */
    public function get(string $code): Hierarchy
    {
        $payload = $this->send(new Request\Load($code), $code);

        return Hierarchy::fromResponse($payload->child('hierarchy'), $this->twinfield->companyReference());
    }

    /**
     * Creates or replaces a reporting structure, with all nodes and access rights, and returns it
     * as Twinfield saved it. Start from `$hierarchy->edit()` to change an existing one.
     *
     *   $hierarchy = $hierarchies->save($hierarchies->get('MGMT')->edit()->name('Management'));
     *
     * @throws HierarchyException With the messages of the reporting structure, its nodes
     *                            (`$message->node`) and access rights, e.g. Touched when someone
     *                            else saved it in the meantime.
     */
    public function save(HierarchyInput $hierarchy): Hierarchy
    {
        $saved = $this->send(new Request\Save($hierarchy->toBody()))->nullableChild('hierarchy')
            ?? throw new UnexpectedResponseException('SaveResponse has no hierarchy and no error message.');

        return Hierarchy::fromResponse($saved, $this->twinfield->companyReference());
    }

    /**
     * Binds general ledger accounts of the company of the client to a node.
     *
     *   $hierarchies->bind($hierarchy->node('A1'), HierarchyAccount::balanceSheet('1100'));
     *
     * @param HierarchyNode|int $node The node, or its ID.
     *
     * @throws HierarchyException E.g. BindingNotUnique when an account is already bound to another node.
     */
    public function bind(HierarchyNode|int $node, HierarchyAccount ...$accounts): void
    {
        $this->send(new Request\Bind(
            self::nodeId($node),
            $this->companyCode(),
            Request\AccountList::of(...$accounts),
        ));
    }

    /**
     * Unbinds general ledger accounts of the company of the client from a node.
     *
     * @param HierarchyNode|int $node The node, or its ID.
     *
     * @throws HierarchyException
     */
    public function unbind(HierarchyNode|int $node, HierarchyAccount ...$accounts): void
    {
        $this->send(new Request\Unbind(
            self::nodeId($node),
            $this->companyCode(),
            Request\AccountList::of(...$accounts),
        ));
    }

    /**
     * Hides the reporting structure.
     *
     * @throws HierarchyNotFoundException
     * @throws HierarchyException
     */
    public function hide(string $code): void
    {
        $this->send(new Request\Hide($code), $code);
    }

    /**
     * Deletes the reporting structure. Delete is in the WSDL, not in the documentation.
     *
     * @throws HierarchyNotFoundException
     * @throws HierarchyException
     */
    public function delete(string $code): void
    {
        $this->send(new Request\Delete($code), $code);
    }

    /**
     * Sends the request and returns the `<Operation>Response` element, after throwing the error
     * messages in `<Operation>Result` (and for Save, in the nodes and access rights). The
     * operation is the short class name of the request.
     *
     * @param string|null $hierarchyCode For Load, Hide and Delete: InvalidCode means not found.
     *
     * @throws HierarchyNotFoundException
     * @throws HierarchyException
     */
    private function send(object $request, ?string $hierarchyCode = null): XmlElement
    {
        $this->companyCode();

        $operation = substr($request::class, (int) strrpos($request::class, '\\') + 1);
        $payload = self::payload($this->twinfield->call('hierarchies', $request), $operation);
        $errors = self::errors(self::messages($payload->child($operation . 'Result'), $operation));
        $saved = $operation === 'Save' ? $payload->nullableChild('hierarchy') : null;

        if ($saved !== null) {
            // The messages of the nodes and access rights are in the returned reporting structure.
            $errors = [...$errors, ...self::nodeErrors($saved->child('RootNode')), ...self::accessErrors($saved)];
        }

        if ($errors === []) {
            return $payload;
        }

        if ($hierarchyCode !== null && $errors[0]->code === HierarchyLoadMessage::InvalidCode) {
            throw new HierarchyNotFoundException($hierarchyCode, $errors[0]->text);
        }

        throw new HierarchyException($operation, $errors);
    }

    private static function payload(SoapResponse $response, string $operation): XmlElement
    {
        $element = $response->payloadElement();
        $expected = $operation . 'Response';

        if ($element === null || $element->localName !== $expected) {
            throw new UnexpectedResponseException(sprintf(
                'Expected <%s>, got %s.',
                $expected,
                $element === null ? 'an empty SOAP body' : '<' . $element->localName . '>',
            ));
        }

        return new XmlElement($element, $expected);
    }

    /**
     * Messages of `<Operation>Result`. Hide and Delete use the messages of Load.
     *
     * @return list<HierarchyMessage>
     */
    private static function messages(XmlElement $result, string $operation): array
    {
        [$itemName, $codes] = match ($operation) {
            'Save' => ['MessageOfHierarchySaveMessage', HierarchySaveMessage::class],
            'Bind' => ['MessageOfHierarchyBindMessage', HierarchyBindMessage::class],
            'Unbind' => ['MessageOfHierarchyUnbindMessage', HierarchyUnbindMessage::class],
            default => ['MessageOfHierarchyLoadMessage', HierarchyLoadMessage::class],
        };

        return array_map(
            static fn (XmlElement $message): HierarchyMessage => self::message($message, $codes),
            $result->list($itemName),
        );
    }

    /**
     * @param class-string<HierarchyMessageCode> $codes
     */
    private static function message(XmlElement $message, string $codes, ?string $node = null): HierarchyMessage
    {
        return new HierarchyMessage(
            type: $message->enum('Type', HierarchyMessageType::class),
            code: $message->enum('Code', $codes),
            text: $message->string('Text'),
            parameters: array_map(self::parameter(...), $message->child('Parameters')->list('string')),
            node: $node,
        );
    }

    /**
     * Error messages of a saved node and the nodes below it.
     *
     * @return list<HierarchyMessage>
     */
    private static function nodeErrors(XmlElement $node): array
    {
        $code = $node->string('Code');
        $errors = self::errors(array_map(
            static fn (XmlElement $message): HierarchyMessage
                => self::message($message, HierarchyNodeMessage::class, $code),
            $node->child('Messages')->list('MessageOfHierarchyNodeMessage'),
        ));

        foreach ($node->child('ChildNodes')->list('HierarchyNode') as $child) {
            $errors = [...$errors, ...self::nodeErrors($child)];
        }

        return $errors;
    }

    /**
     * Error messages of the access rights of a saved reporting structure.
     *
     * @return list<HierarchyMessage>
     */
    private static function accessErrors(XmlElement $hierarchy): array
    {
        $errors = [];

        foreach ($hierarchy->child('AccessRights')->list('HierarchyAccess') as $access) {
            $errors = [...$errors, ...self::errors(array_map(
                static fn (XmlElement $message): HierarchyMessage
                    => self::message($message, HierarchyAccessMessage::class),
                $access->child('Messages')->list('MessageOfHierarchyAccessMessage'),
            ))];
        }

        return $errors;
    }

    /**
     * @param list<HierarchyMessage> $messages
     *
     * @return list<HierarchyMessage>
     */
    private static function errors(array $messages): array
    {
        return array_values(array_filter(
            $messages,
            static fn (HierarchyMessage $message): bool => $message->type === HierarchyMessageType::Error,
        ));
    }

    /**
     * Null for `<string xsi:nil="true"/>`.
     */
    private static function parameter(XmlElement $parameter): ?string
    {
        $nil = $parameter->element->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil');

        return $nil === 'true' ? null : $parameter->text();
    }

    private static function nodeId(HierarchyNode|int $node): int
    {
        return $node instanceof HierarchyNode ? $node->id : $node;
    }

    private function companyCode(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException(
                'Reporting structures need a company in the request header: use company() first.',
            );
    }
}
