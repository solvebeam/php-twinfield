<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Hierarchy;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccessType;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccount;
use SolveBeam\Twinfield\Hierarchy\HierarchyAccountBalanceType;
use SolveBeam\Twinfield\Hierarchy\HierarchyBindMessage;
use SolveBeam\Twinfield\Hierarchy\HierarchyException;
use SolveBeam\Twinfield\Hierarchy\HierarchyInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyLoadMessage;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeInput;
use SolveBeam\Twinfield\Hierarchy\HierarchyNodeMessage;
use SolveBeam\Twinfield\Hierarchy\HierarchyNotFoundException;
use SolveBeam\Twinfield\Hierarchy\HierarchySaveMessage;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Reporting structures (hierarchies.asmx). `hierarchies-search`, `-search-nodes` and
 * `-get-unknown` are recorded and anonymized; `-get` is a recorded and anonymized structure,
 * trimmed to two child nodes and two accounts per node (and one Debit/Credit pair). The others
 * are fictitious, from the WSDL: Save, Bind, Unbind, Hide and Delete are not yet recorded.
 */
final class HierarchyResourceTest extends TestCase
{
    public function testSearchesReportingStructures(): void
    {
        $http = ReplayClient::fixture('hierarchies-search');
        $result = $this->twinfield($http)->hierarchies()->search('*')->get();

        self::assertSame(4, $result->totalRows);
        self::assertSame('HPDMHJ_BT', $result->items[0]->code);
        self::assertSame('Zazfyq qoubpkksbpulketceyn', $result->items[0]->name);
        self::assertStringContainsString('<type>HIE</type>', $this->body($http));
    }

    public function testSearchesTheNodesOfAReportingStructure(): void
    {
        $http = ReplayClient::fixture('hierarchies-search-nodes');
        $result = $this->twinfield($http)->hierarchies()->searchNodes('HPDMHJ_BT')->get();

        self::assertSame('HPDMHJ_BT', $result->items[0]->hierarchyCode);
        self::assertSame('RMX677', $result->items[0]->code);
        self::assertStringContainsString('<type>HND</type>', $this->body($http));
        self::assertStringContainsString(
            '<ArrayOfString><string>hierarchy</string><string>HPDMHJ_BT</string></ArrayOfString>',
            $this->body($http),
        );
    }

    public function testReadsAReportingStructure(): void
    {
        $http = ReplayClient::fixture('hierarchies-get');
        $hierarchy = $this->twinfield($http)->hierarchies()->get('HPDMHJ_BT');

        self::assertSame('http://www.twinfield.com/Load', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('<Load><hierarchyCode>HPDMHJ_BT</hierarchyCode></Load>', $this->body($http));

        self::assertSame('1000', $hierarchy->company->code);
        self::assertSame('HPDMHJ_BT', $hierarchy->code);
        self::assertSame('Zazfyq qoubpkksbpulketceyn', $hierarchy->name);
        self::assertNull($hierarchy->description);
        self::assertSame('HPDMHJ_BT', $hierarchy->rootNode->code);
        self::assertSame(3360, $hierarchy->rootNode->id);
        self::assertCount(2, $hierarchy->rootNode->children);
        self::assertCount(6, $hierarchy->accessRights);

        $node = $hierarchy->node('PIP186');

        self::assertNotNull($node);
        self::assertSame('Gwglhwnc-cixbkeq faieif', $node->name);
        self::assertEquals(
            [
                HierarchyAccount::balanceSheet('64831'),
                HierarchyAccount::balanceSheet('4297', HierarchyAccountBalanceType::Debit),
                HierarchyAccount::balanceSheet('4297', HierarchyAccountBalanceType::Credit),
            ],
            $node->accounts,
        );
        self::assertSame([], $node->children);
        self::assertNull($hierarchy->node('UNKNOWN'));
        self::assertCount(15, $hierarchy->rootNode->allAccounts());
        self::assertSame(GeneralLedgerAccountType::BalanceSheet, $hierarchy->rootNode->allAccounts()[0]->type);
    }

    public function testReadsTheAccessRights(): void
    {
        $hierarchy = $this->twinfield(ReplayClient::fixture('hierarchies-get'))->hierarchies()->get('HPDMHJ_BT');
        $first = $hierarchy->accessRights[0];
        $everyone = $hierarchy->accessRights[5];

        self::assertSame(HierarchyAccessType::Edit, $first->type);
        self::assertNotNull($first->userCode);
        self::assertNull($first->roleCode);
        self::assertNull($first->officeCode);
        self::assertTrue($first->accessRightTabAccessible);

        self::assertSame(HierarchyAccessType::Use, $everyone->type);
        self::assertNull($everyone->userCode);
        self::assertFalse($everyone->accessRightTabAccessible);
    }

    public function testUnknownCodeIsNotFound(): void
    {
        $http = ReplayClient::fixture('hierarchies-get-unknown');

        try {
            $this->twinfield($http)->hierarchies()->get('UNKNOWN');
            self::fail('Expected HierarchyNotFoundException.');
        } catch (HierarchyNotFoundException $exception) {
            self::assertSame('UNKNOWN', $exception->hierarchyCode);
            self::assertStringContainsString('URS-TIQX-QRJ-VQVJJ', $exception->reason);
        }
    }

    public function testRefusesAnUnknownBalanceType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('BalanceType');

        $this->twinfield(ReplayClient::fixture('hierarchies-get-invalid-balance-type'))->hierarchies()->get('MGMT');
    }

    public function testSavesAReportingStructure(): void
    {
        $http = ReplayClient::fixture('hierarchies-save');
        $input = HierarchyInput::create('MGMT', 'Management report')
            ->addNode(
                HierarchyNodeInput::create('R', 'Revenue')
                    ->description('Net revenue')
                    ->accounts(HierarchyAccount::profitAndLoss('8000')),
            )
            ->accessRights(
                HierarchyAccessInput::user('SUPER', HierarchyAccessType::Edit),
                HierarchyAccessInput::everyone(HierarchyAccessType::Use),
            );

        $hierarchy = $this->twinfield($http)->hierarchies()->save($input);

        self::assertSame('http://www.twinfield.com/Save', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Save><hierarchy><Code>MGMT</Code><Name>Management report</Name>'
            . '<RootNode><Code>MGMT</Code><Name>Management report</Name><Accounts/><ChildNodes>'
            . '<HierarchyNode><Code>R</Code><Name>Revenue</Name><Description>Net revenue</Description>'
            . '<Accounts><HierarchyAccount><Type>PNL</Type><Code>8000</Code><BalanceType>Balance</BalanceType>'
            . '</HierarchyAccount></Accounts><ChildNodes/><Touched>0</Touched></HierarchyNode>'
            . '</ChildNodes><Touched>0</Touched></RootNode>'
            . '<AccessRights><HierarchyAccess><Type>Edit</Type><UserCode>SUPER</UserCode>'
            . '<AccessRightTabAccesible>true</AccessRightTabAccesible></HierarchyAccess>'
            . '<HierarchyAccess><Type>Use</Type><AccessRightTabAccesible>false</AccessRightTabAccesible>'
            . '</HierarchyAccess></AccessRights><Touched>0</Touched></hierarchy></Save>',
            $this->body($http),
        );

        self::assertSame(9002, $hierarchy->node('R')?->id);
        self::assertSame(1, $hierarchy->touched);
    }

    public function testSavesAnEditedReportingStructureWithTheNodeIds(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('hierarchies-save'),
            ReplayClient::response('hierarchies-save'),
        );
        $hierarchies = $this->twinfield($http)->hierarchies();
        $hierarchy = $hierarchies->save(HierarchyInput::create('MGMT', 'Management report'));

        $hierarchies->save($hierarchy->edit()->changeNode(
            'R',
            static fn (HierarchyNodeInput $node): HierarchyNodeInput => $node->name('Sales'),
        ));

        self::assertStringContainsString(
            '<RootNode><Id>9001</Id><Code>MGMT</Code><Name>Management report</Name><Accounts/><ChildNodes>'
            . '<HierarchyNode><Id>9002</Id><Code>R</Code><Name>Sales</Name>',
            $this->body($http),
        );
        self::assertStringContainsString('</AccessRights><Touched>1</Touched></hierarchy>', $this->body($http));
    }

    public function testThrowsTheErrorsOfTheNodesWhenSaving(): void
    {
        $http = ReplayClient::fixture('hierarchies-save-node-error');

        try {
            $this->twinfield($http)->hierarchies()->save(HierarchyInput::create('MGMT', 'Management report'));
            self::fail('Expected HierarchyException.');
        } catch (HierarchyException $exception) {
            self::assertSame('Save', $exception->operation);
            self::assertCount(2, $exception->messages, 'The warning of the access right is left out.');
            self::assertSame(HierarchySaveMessage::InvalidNodes, $exception->messages[0]->code);
            self::assertNull($exception->messages[0]->node);
            self::assertSame(HierarchyNodeMessage::NodeNotUnique, $exception->messages[1]->code);
            self::assertSame('R', $exception->messages[1]->node);
            self::assertSame(['R'], $exception->messages[1]->parameters);
            self::assertStringContainsString('(and 1 more errors)', $exception->getMessage());
        }
    }

    public function testThrowsTouchedWhenSomeoneElseSavedIt(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('hierarchies-save-touched'))->hierarchies()
                ->save(HierarchyInput::create('MGMT', 'Management report'));
            self::fail('Expected HierarchyException.');
        } catch (HierarchyException $exception) {
            self::assertSame(HierarchySaveMessage::Touched, $exception->messages[0]->code);
        }
    }

    public function testBindsAccountsToANode(): void
    {
        $http = ReplayClient::fixture('hierarchies-bind');

        $this->twinfield($http)->hierarchies()->bind(
            9002,
            HierarchyAccount::balanceSheet('1100'),
            HierarchyAccount::balanceSheet('1110', HierarchyAccountBalanceType::Debit),
        );

        self::assertSame('http://www.twinfield.com/Bind', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Bind><node>9002</node><office>1000</office><accounts>'
            . '<HierarchyAccount><Type>BAS</Type><Code>1100</Code><BalanceType>Balance</BalanceType></HierarchyAccount>'
            . '<HierarchyAccount><Type>BAS</Type><Code>1110</Code><BalanceType>Debit</BalanceType></HierarchyAccount>'
            . '</accounts></Bind>',
            $this->body($http),
        );
    }

    public function testBindsToANodeOfAReportingStructure(): void
    {
        $http = new ReplayClient(ReplayClient::response('hierarchies-get'), ReplayClient::response('hierarchies-bind'));
        $hierarchies = $this->twinfield($http)->hierarchies();
        $node = $hierarchies->get('HPDMHJ_BT')->node('PIP186');

        self::assertNotNull($node);

        $hierarchies->bind($node, HierarchyAccount::balanceSheet('1100'));

        self::assertStringContainsString('<Bind><node>' . $node->id . '</node>', $this->body($http));
    }

    public function testThrowsTheErrorsOfBind(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('hierarchies-bind-not-unique'))->hierarchies()
                ->bind(9002, HierarchyAccount::balanceSheet('1100'));
            self::fail('Expected HierarchyException.');
        } catch (HierarchyException $exception) {
            self::assertSame('Bind', $exception->operation);
            self::assertSame(HierarchyBindMessage::BindingNotUnique, $exception->messages[0]->code);
            self::assertSame(['1100', null], $exception->messages[0]->parameters);
        }
    }

    public function testUnbindsAccountsFromANode(): void
    {
        $http = ReplayClient::fixture('hierarchies-unbind');

        $this->twinfield($http)->hierarchies()->unbind(9002, HierarchyAccount::profitAndLoss('8000'));

        self::assertSame('http://www.twinfield.com/Unbind', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<Unbind><node>9002</node><office>1000</office><accounts><HierarchyAccount><Type>PNL</Type>'
            . '<Code>8000</Code><BalanceType>Balance</BalanceType></HierarchyAccount></accounts></Unbind>',
            $this->body($http),
        );
    }

    public function testHidesAReportingStructure(): void
    {
        $http = ReplayClient::fixture('hierarchies-hide');

        $this->twinfield($http)->hierarchies()->hide('MGMT');

        self::assertSame('http://www.twinfield.com/Hide', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('<Hide><hierarchyCode>MGMT</hierarchyCode></Hide>', $this->body($http));
    }

    public function testDeletesAReportingStructure(): void
    {
        $http = ReplayClient::fixture('hierarchies-delete');

        $this->twinfield($http)->hierarchies()->delete('MGMT');

        self::assertSame('http://www.twinfield.com/Delete', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('<Delete><hierarchyCode>MGMT</hierarchyCode></Delete>', $this->body($http));
    }

    public function testThrowsWhenNotAllowed(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('hierarchies-delete-not-allowed'))->hierarchies()->delete('MGMT');
            self::fail('Expected HierarchyException.');
        } catch (HierarchyException $exception) {
            self::assertSame(
                HierarchyLoadMessage::UserRoleNotAllowedToPerformThisAction,
                $exception->messages[0]->code,
            );
        }
    }

    public function testThrowsOnAnUnexpectedResponse(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected <HideResponse>, got <DeleteResponse>.');

        $this->twinfield(ReplayClient::fixture('hierarchies-delete'))->hierarchies()->hide('MGMT');
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);

        $twinfield->hierarchies()->get('MGMT');
    }

    private function body(ReplayClient $http): string
    {
        $body = (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());

        // Without the namespace prefixes, so the assertions read like the WSDL.
        return (string) preg_replace('~ xmlns(:\w+)?="[^"]*"~', '', $body);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
