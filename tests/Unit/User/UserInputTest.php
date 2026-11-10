<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\User;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\User\SingleSignOn;
use SolveBeam\Twinfield\User\TemplateCompanyAccess;
use SolveBeam\Twinfield\User\UserCulture;
use SolveBeam\Twinfield\User\UserInput;
use SolveBeam\Twinfield\User\UserRole;
use SolveBeam\Twinfield\User\UserSummary;
use SolveBeam\Twinfield\User\UserType;

final class UserInputTest extends TestCase
{
    public function testSendsOnlyWhatIsSet(): void
    {
        self::assertSame(
            '<user><code>JDOE</code><name>John Doe</name></user>',
            UserInput::create('JDOE', 'John Doe')->toXml()->toXml(),
        );
    }

    public function testBuildsAllSettingsInTheDocumentedOrder(): void
    {
        $input = UserInput::create('JDOE', 'John Doe')
            ->federation('example.com', 'jdoe@example.com')
            ->expenseDefaults(generalLedgerAccount: '8000', activity: 'A001')
            ->weekHours(32)
            ->timeAuthoriser(new UserSummary(TestTokens::organisation(), 'JSMITH', 'Jane Smith'))
            ->disable(new DateTimeImmutable('2026-07-01 08:00'), new DateTimeImmutable('2026-08-01'))
            ->passwordExpires(new DateTimeImmutable('2027-12-31'))
            ->singleSignOn(SingleSignOn::Disabled)
            ->templateCompanyAccess(TemplateCompanyAccess::CompanyCreationOnly)
            ->companies(
                '1000',
                new OfficeSummary(
                    TestTokens::organisation(),
                    '1001',
                    'Example Trading B.V.',
                    '7c9e6679-7425-40de-944b-e07fc1f90ae7',
                    'NL',
                    1,
                    21,
                ),
                '1000',
            )
            ->defaultCompany('1000')
            ->smsLogin('+31612345678')
            ->invite()
            ->fileManagerQuota(20)
            ->exchangeQuota(1)
            ->role(new UserRole('LVL2', 'Level 2', null, 3))
            ->email('john.doe@example.com')
            ->culture(UserCulture::English)
            ->password('s3cret!')
            ->demo(false)
            ->acceptExtraCost()
            ->type(UserType::Regular)
            ->shortName('John');

        self::assertSame(
            '<user><code>JDOE</code><name>John Doe</name><shortname>John</shortname><type>regular</type>'
            . '<acceptextracost>true</acceptextracost><demo>false</demo><password>s3cret!</password>'
            . '<culture>en-GB</culture><email>john.doe@example.com</email><role>LVL2</role>'
            . '<exchangequota>1</exchangequota><filemanagerquota>20</filemanagerquota>'
            . '<invitationstatus>Invited</invitationstatus>'
            . '<smslogin><phonenumber>+31612345678</phonenumber><service>1</service></smslogin>'
            . '<office><default>1000</default><templatecompanyaccess>useonlyforcompanycreation</templatecompanyaccess>'
            . '<offices><office>1000</office><office>1001</office></offices></office>'
            . '<account><sso>0</sso><expire>20271231</expire><disable>'
            . '<from>20260701080000</from><to>20260801000000</to></disable></account>'
            . '<time><authoriser>JSMITH</authoriser><weekhours>32</weekhours></time>'
            . '<expenses><dimension1>8000</dimension1><dimension4>A001</dimension4></expenses>'
            . '<federation><domain>example.com</domain><email>jdoe@example.com</email></federation></user>',
            $input->toXml()->toXml(),
        );
    }

    public function testNullSendsEmptyElements(): void
    {
        $xml = UserInput::create('JDOE', 'John Doe')
            ->shortName('')
            ->defaultCompany(null)
            ->timeAuthoriser(null)
            ->weekHours(null)
            ->smsLogin(null, enabled: false)
            ->expenseDefaults(project: null)
            ->enable()
            ->toXml()
            ->toXml();

        self::assertSame(
            '<user><code>JDOE</code><name>John Doe</name><shortname/>'
            . '<smslogin><phonenumber/><service>0</service></smslogin><office><default/></office>'
            . '<account><disable><from/><to/></disable></account><time><authoriser/><weekhours/></time>'
            . '<expenses><dimension3/></expenses></user>',
            $xml,
        );
    }

    public function testIsImmutable(): void
    {
        $input = UserInput::create('JDOE', 'John Doe');
        $input->email('john.doe@example.com');

        self::assertSame('<user><code>JDOE</code><name>John Doe</name></user>', $input->toXml()->toXml());
    }

    public function testGrantsAndRevokesAccessOnTheCompanyList(): void
    {
        $input = UserInput::create('JDOE', 'John Doe')
            ->companies('1000', '1001')
            ->grantAccessTo('1002', '1000')
            ->revokeAccessTo('1001');

        self::assertStringContainsString(
            '<offices><office>1000</office><office>1002</office></offices>',
            $input->toXml()->toXml(),
        );
    }

    public function testGrantAccessNeedsTheCompanyList(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('the user keeps access to all companies');

        UserInput::create('JDOE', 'John Doe')->grantAccessTo('1000');
    }

    public function testInviteNeedsCultureAndRole(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Inviting user JDOE needs culture() and role().');

        UserInput::create('JDOE', 'John Doe')->role('LVL1')->invite()->toXml();
    }

    public function testDemoIsOnlyForClientsOfAnAccountant(): void
    {
        UserInput::create('JDOE', 'John Doe')->type(UserType::AccountantCustomer)->demo()->toXml();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('demo() is only for UserType::AccountantCustomer');

        UserInput::create('JDOE', 'John Doe')->type(UserType::Regular)->demo()->toXml();
    }

    public function testPasswordExpiryDoesNotGoWithSingleSignOn(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not go with single sign-on');

        UserInput::create('JDOE', 'John Doe')
            ->singleSignOn(SingleSignOn::Required)
            ->passwordExpires(new DateTimeImmutable('2027-01-01'))
            ->toXml();
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'lower case code' => [static fn () => UserInput::create('jdoe', 'John'), '"jdoe" is not a code'];
        yield 'empty name' => [static fn () => UserInput::create('JDOE', ''), 'User name must not be empty.'];
        yield 'long name' => [static fn () => UserInput::create('JDOE', str_repeat('x', 41)), 'at most 40 characters'];
        yield 'long short name' => [
            static fn () => UserInput::create('JDOE', 'J')->shortName(str_repeat('x', 21)),
            'at most 20 characters',
        ];
        yield 'invalid email' => [
            static fn () => UserInput::create('JDOE', 'J')->email('john.doe'),
            '"john.doe" is not an email address.',
        ];
        yield 'quoted email' => [
            static fn () => UserInput::create('JDOE', 'J')->email('"John Doe"@example.com'),
            '""John Doe"@example.com" is not an email address.',
        ];
        yield 'invalid federation email' => [
            static fn () => UserInput::create('JDOE', 'J')->federation('example.com', 'john.doe@'),
            '"john.doe@" is not an email address.',
        ];
        yield 'empty password' => [
            static fn () => UserInput::create('JDOE', 'J')->password(''),
            'Password must not be empty.',
        ];
        yield 'negative quota' => [
            static fn () => UserInput::create('JDOE', 'J')->fileManagerQuota(-1),
            'File Manager quota must be 0 or more',
        ];
        yield 'week hours' => [
            static fn () => UserInput::create('JDOE', 'J')->weekHours(169),
            'Week hours must be 0 to 168, 169 given.',
        ];
        yield 'no companies' => [
            static fn () => UserInput::create('JDOE', 'J')->companies(),
            'Give at least one company.',
        ];
        yield 'empty company' => [
            static fn () => UserInput::create('JDOE', 'J')->defaultCompany(''),
            'Company code must not be empty.',
        ];
        yield 'enabled before disabled' => [
            static fn () => UserInput::create('JDOE', 'J')->disable(
                new DateTimeImmutable('2026-08-01'),
                new DateTimeImmutable('2026-07-01'),
            ),
            'disabled until after 2026-08-01',
        ];
    }

    #[DataProvider('invalidInput')]
    public function testRefusesInvalidInput(callable $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }
}
