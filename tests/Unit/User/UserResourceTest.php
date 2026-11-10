<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\User;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;
use SolveBeam\Twinfield\User\InvitationStatus;
use SolveBeam\Twinfield\User\SingleSignOn;
use SolveBeam\Twinfield\User\TemplateCompanyAccess;
use SolveBeam\Twinfield\User\UserAlreadyExistsException;
use SolveBeam\Twinfield\User\UserCulture;
use SolveBeam\Twinfield\User\UserDeleteReason;
use SolveBeam\Twinfield\User\UserInput;
use SolveBeam\Twinfield\User\UserNotFoundException;
use SolveBeam\Twinfield\User\UserStatus;
use SolveBeam\Twinfield\User\UserSummary;
use SolveBeam\Twinfield\User\UserType;

final class UserResourceTest extends TestCase
{
    public function testSearchesUsers(): void
    {
        $http = ReplayClient::fixture('finder-users');

        $page = $this->twinfield($http)
            ->users()
            ->search('J*')
            ->inCode()
            ->withMutualCompanies()
            ->withoutAccessRules()
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals(
            [
                new UserSummary(TestTokens::organisation(), 'ADMIN', 'Example Admin'),
                new UserSummary(TestTokens::organisation(), 'JDOE', 'John Doe'),
            ],
            iterator_to_array($page),
        );

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('USR', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('J*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame(
            ['mutualoffices', '1', 'accessrules', '0'],
            array_map(
                static fn (\DOMNode|\DOMNameSpaceNode $node): ?string => $node->nodeValue,
                iterator_to_array($xpath->query('//tw:Search/tw:options/tw:ArrayOfString/tw:string') ?: []),
            ),
        );
    }

    public function testListsAllUsers(): void
    {
        $users = $this->twinfield(ReplayClient::fixture('finder-users'))->users()->list();

        self::assertSame(['ADMIN', 'JDOE'], array_map(static fn (UserSummary $user): string => $user->code, $users));
    }

    public function testReadsAUser(): void
    {
        $http = ReplayClient::fixture('user-read');

        $user = $this->twinfield($http)->users()->get('JDOE');

        self::assertSame('<read><type>user</type><code>JDOE</code></read>', $this->xmlRequest($http->lastRequest()));

        self::assertSame('JDOE', $user->code);
        self::assertSame('John Doe', $user->name);
        self::assertSame('John', $user->shortName);
        self::assertSame('john.doe@example.com', $user->email);
        self::assertSame(UserStatus::Active, $user->status);
        self::assertFalse($user->isCurrentUser);
        self::assertSame(UserType::Accountant, $user->type);
        self::assertSame(UserCulture::Dutch, $user->culture);
        self::assertSame('LVL1', $user->role->code);
        self::assertSame('Level 1', $user->role->name);
        self::assertSame(2, $user->role->level);
        self::assertFalse($user->demo);
        self::assertSame(0, $user->exchangeQuota);
        self::assertSame(5, $user->fileManagerQuota);
        self::assertSame(InvitationStatus::Active, $user->invitationStatus);
        self::assertSame('john.doe@example.com', $user->accountEmail);
        self::assertTrue($user->smsLogin->enabled);
        self::assertSame('+31612345678', $user->smsLogin->phoneNumber);

        self::assertTrue($user->companies->restricted);
        self::assertSame(['1000', '1001'], $user->companies->codes());
        self::assertEquals(new NamedReference('1001', 'Example Trading B.V.'), $user->companies->companies[1]);
        self::assertSame('1000', $user->companies->defaultCompany?->code);
        self::assertSame(TemplateCompanyAccess::UseAndManage, $user->companies->templateCompanyAccess);
        self::assertTrue($user->hasAccessTo('1001'));
        self::assertFalse($user->hasAccessTo('2000'));
        self::assertTrue($user->hasAccessTo(TestTokens::company('1001')));
        self::assertFalse(
            $user->hasAccessTo(
                new CompanyReference(new Organisation('7c9e6679-7425-40de-944b-e07fc1f90ae7', 'OTHER'), '1001'),
            ),
            'Company 1001 of another organisation is another company.',
        );
        self::assertEquals(TestTokens::organisation(), $user->organisation);

        self::assertFalse($user->account->locked);
        self::assertEquals(new DateTimeImmutable('2026-11-06 15:51:35'), $user->account->lastLogon);
        self::assertSame(0, $user->account->failedLogons);
        self::assertSame(SingleSignOn::Disabled, $user->account->singleSignOn);
        self::assertEquals(new DateTimeImmutable('2027-12-31'), $user->account->passwordExpires);
        self::assertEquals(new DateTimeImmutable('2026-07-01'), $user->account->disabledFrom);
        self::assertEquals(new DateTimeImmutable('2026-08-01'), $user->account->disabledUntil);
        self::assertTrue($user->account->isDisabledAt(new DateTimeImmutable('2026-07-15')));
        self::assertFalse($user->account->isDisabledAt(new DateTimeImmutable('2026-08-01')));
        self::assertFalse($user->account->isDisabledAt(new DateTimeImmutable('2026-06-30 23:59:59')));

        self::assertEquals(new NamedReference('JSMITH', 'Jane Smith', 'Jane'), $user->time->authoriser);
        self::assertSame(32, $user->time->weekHours);
        self::assertSame('8000', $user->expenseDefaults->generalLedgerAccount?->code);
        self::assertNull($user->expenseDefaults->costCenter);
        self::assertSame('P0001', $user->expenseDefaults->project?->code);
        self::assertNull($user->expenseDefaults->activity);
        self::assertNull($user->federation);

        self::assertEquals(new DateTimeImmutable('2019-03-12 09:15:00'), $user->created);
        self::assertEquals(new DateTimeImmutable('2026-11-03 13:04:38'), $user->modified);
        self::assertSame(141, $user->touched);
        self::assertEquals(new NamedReference('ADMIN', 'Example Admin', 'Admin'), $user->modifiedBy);
    }

    public function testReadsAUserWithAccessToAllCompaniesAndEmptySettings(): void
    {
        $user = $this->twinfield(ReplayClient::fixture('user-read-all-companies'))->users()->get('API');

        self::assertTrue($user->isCurrentUser);
        self::assertNull($user->shortName);
        self::assertNull($user->accountEmail);
        self::assertSame(InvitationStatus::NotInvited, $user->invitationStatus);
        self::assertFalse($user->smsLogin->enabled);
        self::assertNull($user->smsLogin->phoneNumber);
        self::assertFalse($user->companies->restricted);
        self::assertSame([], $user->companies->companies);
        self::assertNull($user->companies->defaultCompany);
        self::assertSame(TemplateCompanyAccess::None, $user->companies->templateCompanyAccess);
        self::assertTrue($user->hasAccessTo('9999'), 'A user that is not restricted has access to every company.');
        self::assertNull($user->account->lastLogon);
        self::assertSame(SingleSignOn::Enabled, $user->account->singleSignOn);
        self::assertNull($user->account->passwordExpires);
        self::assertFalse($user->account->isDisabledAt(new DateTimeImmutable()));
        self::assertNull($user->time->authoriser);
        self::assertNull($user->time->weekHours);
        self::assertNull($user->expenseDefaults->generalLedgerAccount);
    }

    public function testReadsFederation(): void
    {
        $fixture = (string) ReplayClient::response('user-read')->getBody();
        $xml = str_replace(
            '&lt;performance&gt;',
            '&lt;federation&gt;&lt;domain&gt;example.com&lt;/domain&gt;&lt;email'
                . '&gt;jdoe@example.com&lt;/email&gt;&lt;/federation&gt;&lt;performance&gt;',
            $fixture,
        );

        $user = $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->users()->get('JDOE');

        self::assertSame('example.com', $user->federation?->domain);
        self::assertSame('jdoe@example.com', $user->federation->email);
        self::assertStringContainsString(
            '<federation><domain>example.com</domain><email>jdoe@example.com</email></federation>',
            $user->edit()->toXml()->toXml(),
        );
    }

    public function testReadsTheCurrentUserFromTheAccessToken(): void
    {
        $http = ReplayClient::fixture('user-read-all-companies');

        $user = $this->twinfield($http, userCode: 'API')->users()->current();

        self::assertSame('API', $user->code);
        self::assertSame('<read><type>user</type><code>API</code></read>', $this->xmlRequest($http->lastRequest()));
    }

    public function testNeedsACompany(): void
    {
        $http = ReplayClient::fixture('user-read');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('any company works');

        try {
            $this->twinfield($http)->withoutCompany()->users()->get('JDOE');
        } finally {
            self::assertCount(0, $http->requests);
        }
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('user-read-not-found'))->users()->get('NOPE');
            self::fail('Expected a UserNotFoundException.');
        } catch (UserNotFoundException $exception) {
            self::assertSame('NOPE', $exception->userCode);
            self::assertSame('Gebruiker NOPE bestaat niet.', $exception->reason);
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFind(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('user-read-not-found'))->users()->find('NOPE'));
        self::assertSame('JDOE', $this->twinfield(ReplayClient::fixture('user-read'))->users()->find('JDOE')?->code);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unexpectedResponses(): iterable
    {
        yield 'unknown status' => ['status="active" result', 'status="archived" result', 'user/@status is "archived"'];
        yield 'unknown type' => ['>accountant</type>', '>admin</type>', 'user/type is "admin"'];
        yield 'unknown culture' => ['>nl-NL</culture>', '>nl-BE</culture>', 'user/culture is "nl-BE"'];
        yield 'empty email' => ['<email>john.doe@example.com</email>', '<email></email>', 'user/email is empty'];
        yield 'role without code' => [
            'level="2">LVL1</role>',
            'level="2"></role>',
            'user/role is "", expected a role code',
        ];
        yield 'unknown invitation' => [
            '>Active</invitationstatus>',
            '>Pending</invitationstatus>',
            'user/invitationstatus is "Pending"',
        ];
        yield 'unknown template access' => [
            '>useandmanage<',
            '>everything<',
            'user/office/templatecompanyaccess is "everything"',
        ];
        yield 'invalid sso' => ['<sso>0</sso>', '<sso>yes</sso>', 'user/account/sso is "yes"'];
        yield 'invalid last logon' => [
            'lastlogon="20261106155135"',
            'lastlogon="2026-11-06"',
            'user/account/@lastlogon is "2026-11-06"',
        ];
        yield 'enabled before disabled' => [
            '<to>20260801000000</to>',
            '<to>20260601000000</to>',
            'disabled until 2026-06-01 00:00:00',
        ];
        yield 'week hours' => ['<weekhours>32<', '<weekhours>200<', 'Week hours must be 0 to 168, 200 given'];
        yield 'no modifier' => [
            '<user name="Example Admin" shortname="Admin">ADMIN</user>',
            '',
            'user/user is missing',
        ];
        yield 'other element in offices' => [
            '<office name="Example Trading B.V." shortname="">1001</office>',
            '<company>1001</company>',
            'user/office/offices contains <company>',
        ];
    }

    #[DataProvider('unexpectedResponses')]
    public function testThrowsOnUnexpectedResponses(string $search, string $replace, string $message): void
    {
        $fixture = (string) ReplayClient::response('user-read')->getBody();
        $xml = str_replace(htmlspecialchars($search, ENT_NOQUOTES), htmlspecialchars($replace, ENT_NOQUOTES), $fixture);

        self::assertNotSame($fixture, $xml, 'The fixture must contain the text to replace.');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->users()->get('JDOE');
    }

    public function testEditRoundTripsTheSettings(): void
    {
        $user = $this->twinfield(ReplayClient::fixture('user-read'))->users()->get('JDOE');

        self::assertSame(
            '<user><code>JDOE</code><name>John Doe</name><shortname>John</shortname><type>accountant</type>'
            . '<culture>nl-NL</culture><email>john.doe@example.com</email><role>LVL1</role>'
            . '<exchangequota>0</exchangequota><filemanagerquota>5</filemanagerquota>'
            . '<smslogin><phonenumber>+31612345678</phonenumber><service>1</service></smslogin>'
            . '<office><default>1000</default><templatecompanyaccess>useandmanage</templatecompanyaccess>'
            . '<offices><office>1000</office><office>1001</office></offices></office>'
            . '<account><sso>0</sso><expire>20271231</expire><disable>'
            . '<from>20260701000000</from><to>20260801000000</to></disable></account>'
            . '<time><authoriser>JSMITH</authoriser><weekhours>32</weekhours></time>'
            . '<expenses><dimension1>8000</dimension1><dimension2/>'
            . '<dimension3>P0001</dimension3><dimension4/></expenses></user>',
            $user->edit()->toXml()->toXml(),
        );
    }

    public function testEditLeavesOutTheCompanyListAndExpiryOfAnUnrestrictedSingleSignOnUser(): void
    {
        $xml = $this->twinfield(ReplayClient::fixture('user-read-all-companies'))
            ->users()
            ->get('API')
            ->edit()
            ->toXml()
            ->toXml();

        self::assertStringContainsString(
            '<office><default/><templatecompanyaccess>nouse</templatecompanyaccess></office>',
            $xml,
        );
        self::assertStringContainsString('<account><sso>1</sso><disable><from/><to/></disable></account>', $xml);
        self::assertStringContainsString('<shortname/>', $xml, 'An empty short name is sent empty.');
    }

    public function testSavesAndReadsBack(): void
    {
        $http = new ReplayClient(ReplayClient::response('user-save'), ReplayClient::response('user-read'));

        $user = $this->twinfield($http)->users()->save(
            UserInput::create('JDOE', 'John Doe')->email('john.doe@example.com')->role('LVL1'),
        );

        self::assertSame(32, $user->time->weekHours, 'The user is read back after saving.');
        self::assertCount(2, $http->requests);
        self::assertSame(
            '<user><code>JDOE</code><name>John Doe</name><email>john.doe@example.com</email><role>LVL1</role></user>',
            $this->xmlRequest($http->requests[0]),
        );
        self::assertSame('<read><type>user</type><code>JDOE</code></read>', $this->xmlRequest($http->requests[1]));
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('user-save-error'))->users()->save(
                UserInput::create('JDOE', 'John Doe')->role('XXX'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            $errors = $exception->result->errors();

            self::assertCount(1, $errors);
            self::assertSame('user/role', $errors[0]->path);
            self::assertSame('Rol XXX bestaat niet.', $errors[0]->text);
        }
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('user-read-not-found'),
            ReplayClient::response('user-save'),
            ReplayClient::response('user-read'),
        );

        $user = $this->twinfield($http)->users()->create(UserInput::create('JDOE', 'John Doe'));

        self::assertSame('JDOE', $user->code);
        self::assertCount(3, $http->requests);
        self::assertSame('<user><code>JDOE</code><name>John Doe</name></user>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('user-read');

        try {
            $this->twinfield($http)->users()->create(UserInput::create('JDOE', 'Other'));
            self::fail('Expected a UserAlreadyExistsException.');
        } catch (UserAlreadyExistsException $exception) {
            self::assertSame('User JDOE already exists.', $exception->getMessage());
            self::assertSame('JDOE', $exception->userCode);
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdateRefusesAMissingUser(): void
    {
        $http = ReplayClient::fixture('user-read-not-found');

        $this->expectException(UserNotFoundException::class);

        try {
            $this->twinfield($http)->users()->update(UserInput::create('NOPE', 'Nobody'));
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletesWithAReason(): void
    {
        $http = ReplayClient::fixture('user-delete');

        $result = $this->twinfield($http)->users()->delete(
            'JDOE',
            UserDeleteReason::NoLongerOperative,
            'Left the company',
        );

        self::assertSame(DeleteResult::Deleted, $result);
        self::assertSame(
            '<user status="deleted"><code>JDOE</code><deletereason><code>nolongeroperative</code>'
            . '<comment>Left the company</comment></deletereason></user>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAUserInUse(): void
    {
        $http = ReplayClient::fixture('user-delete-hidden');

        self::assertSame(DeleteResult::Hidden, $this->twinfield($http)->users()->delete('JDOE'));
        self::assertSame('<user status="deleted"><code>JDOE</code></user>', $this->xmlRequest($http->lastRequest()));
    }

    public function testDeleteCommentNeedsAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(ReplayClient::fixture('user-delete'))->users()->delete('JDOE', comment: 'Why');
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('user-read-not-found'))->users()->delete('NOPE');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('user-activate');

        $this->twinfield($http)->users()->activate('JDOE');

        self::assertSame('<user status="active"><code>JDOE</code></user>', $this->xmlRequest($http->lastRequest()));
    }

    private function twinfield(ReplayClient $http, ?string $userCode = null): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(
            TestTokens::provider('https://example.test', $userCode ?? 'JDOE'),
            $http,
            $psr17,
            $psr17,
        )->company('1000');
    }

    private function soapXpath(RequestInterface $request): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $xmlRequest = $this->soapXpath($request)->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
