<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\EmailAddress;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A user to create or update. Immutable: every method returns a new input.
 *
 *   $input = UserInput::create('JDOE', 'John Doe')
 *       ->email('john.doe@example.com')
 *       ->type(UserType::Regular)
 *       ->culture(UserCulture::Dutch)
 *       ->role('LVL1')
 *       ->companies('1000', '1001')
 *       ->defaultCompany('1000')
 *       ->invite();
 *
 * Only what you set is sent: anything else gets the Twinfield default when creating, and stays
 * as it is when updating. Setting a value to null sends it empty. For an existing user, start
 * from `$user->edit()`.
 *
 * A new user can cost money: Twinfield charges per user, depending on the subscription and the
 * role. See acceptExtraCost().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/users/
 */
final readonly class UserInput
{
    /**
     * Elements in the order of the documentation; `a/b` is element `b` in `a`. The company list
     * (`office/offices`) follows `office/templatecompanyaccess`.
     */
    private const array ELEMENTS = [
        'name',
        'shortname',
        'type',
        'acceptextracost',
        'demo',
        'password',
        'culture',
        'email',
        'role',
        'exchangequota',
        'filemanagerquota',
        'invitationstatus',
        'smslogin/phonenumber',
        'smslogin/service',
        'office/default',
        'office/templatecompanyaccess',
        'account/sso',
        'account/expire',
        'account/disable/from',
        'account/disable/to',
        'time/authoriser',
        'time/weekhours',
        'expenses/dimension1',
        'expenses/dimension2',
        'expenses/dimension3',
        'expenses/dimension4',
        'federation/domain',
        'federation/email',
    ];

    /**
     * @param array<string, ?string> $values Element (see ELEMENTS) => value; null sends an
     *     empty element.
     * @param ?list<string> $companies Codes of the companies a restricted user has access to;
     *     null when not set: the list is not sent.
     */
    private function __construct(
        public string $code,
        private array $values,
        private ?array $companies,
    ) {
        XmlValue::code($code);
    }

    /**
     * @param string $code User code: A-Z, 0-9, `_`, `-` or `+`, at most 16 characters, e.g. `JDOE`.
     * @param string $name At most 40 characters.
     */
    public static function create(string $code, string $name): self
    {
        return (new self($code, [], null))->name($name);
    }

    /**
     * At most 40 characters.
     */
    public function name(string $name): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('User name must not be empty.');
        }

        return $this->set('name', XmlValue::maxLength('user name', $name, 40));
    }

    /**
     * At most 20 characters. Null or an empty string clears it.
     */
    public function shortName(?string $shortName): self
    {
        if ($shortName === null || $shortName === '') {
            return $this->set('shortname', null);
        }

        return $this->set('shortname', XmlValue::maxLength('user short name', $shortName, 20));
    }

    /**
     * At most 128 characters, and an address Twinfield accepts: see EmailAddress for the rules.
     *
     * @throws InvalidArgumentException When Twinfield would refuse the address.
     */
    public function email(EmailAddress|string $email): self
    {
        return $this->set('email', XmlValue::maxLength('user email', EmailAddress::of($email)->value, 128));
    }

    /**
     * Checked against the type of the organisation: use `UserType::Regular` for an organisation
     * that is not an accountancy.
     */
    public function type(UserType $type): self
    {
        return $this->set('type', $type->value);
    }

    /**
     * Language in which Twinfield is shown. Required with invite().
     */
    public function culture(UserCulture $culture): self
    {
        return $this->set('culture', $culture->value);
    }

    /**
     * Role, e.g. `LVL1`, or the role of another user (`$other->role`). Required with invite().
     * Changing the role can change the costs: see acceptExtraCost().
     */
    public function role(string|UserRole $role): self
    {
        return $this->set('role', XmlValue::code(is_string($role) ? $role : $role->code));
    }

    /**
     * Accept extra costs when the role (subscription) of the user changes. Without it, Twinfield
     * refuses a change that costs more.
     */
    public function acceptExtraCost(bool $accept = true): self
    {
        return $this->set('acceptextracost', XmlValue::boolean($accept));
    }

    /**
     * Only use the user for training. Only for clients of an accountant
     * (`UserType::AccountantCustomer`).
     */
    public function demo(bool $demo = true): self
    {
        return $this->set('demo', XmlValue::boolean($demo));
    }

    /**
     * Password for logging in without a Wolters Kluwer account. Never returned by Twinfield.
     */
    public function password(#[\SensitiveParameter] string $password): self
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password must not be empty.');
        }

        return $this->set('password', $password);
    }

    /**
     * Twinfield Analysis quota.
     */
    public function exchangeQuota(int $quota): self
    {
        return $this->set('exchangequota', (string) self::notNegative('Exchange quota', $quota));
    }

    /**
     * File Manager quota.
     */
    public function fileManagerQuota(int $quota): self
    {
        return $this->set('filemanagerquota', (string) self::notNegative('File Manager quota', $quota));
    }

    /**
     * Invite the user to a Wolters Kluwer account, when creating a user or updating one that is
     * not invited yet. Needs culture() and role(), and only works for organisations that
     * Twinfield enabled for invitations.
     */
    public function invite(): self
    {
        return $this->set('invitationstatus', InvitationStatus::Invited->value);
    }

    /**
     * Log in with a code by text message (SMS logon package), sent to the phone number. Null
     * clears the phone number.
     */
    public function smsLogin(?string $phoneNumber, bool $enabled = true): self
    {
        return $this
            ->set('smslogin/phonenumber', $phoneNumber === '' ? null : $phoneNumber)
            ->set('smslogin/service', $enabled ? '1' : '0');
    }

    /**
     * Company opened after logging in; null lets the user choose a company after logging in.
     */
    public function defaultCompany(string|CompanyReference|Office|OfficeSummary|null $company): self
    {
        return $this->set('office/default', $company === null ? null : self::companyCode($company));
    }

    /**
     * Restricts the user to these companies; replaces the list. Without it, the list is not sent.
     *
     *   $input->companies('1000', '1001');
     *   $input->companies(...$twinfield->offices()->list());
     */
    public function companies(string|CompanyReference|Office|OfficeSummary ...$companies): self
    {
        if ($companies === []) {
            throw new InvalidArgumentException('Give at least one company.');
        }

        return $this->with(['companies' => array_values(array_unique(array_map(self::companyCode(...), $companies)))]);
    }

    /**
     * Adds companies to the list of a restricted user, e.g. `$user->edit()->grantAccessTo('1002')`.
     *
     * @throws LogicException When the company list is not set: start from edit() of a restricted
     *     user, or set the full list with companies(). Sending only the new companies would
     *     take away access to the others.
     */
    public function grantAccessTo(string|CompanyReference|Office|OfficeSummary ...$companies): self
    {
        return $this->companies(...$this->companyList('grantAccessTo'), ...$companies);
    }

    /**
     * Removes companies from the list of a restricted user, e.g. `$user->edit()->revokeAccessTo('1002')`.
     *
     * @throws LogicException When the company list is not set, see grantAccessTo().
     */
    public function revokeAccessTo(string|CompanyReference|Office|OfficeSummary ...$companies): self
    {
        $revoked = array_map(self::companyCode(...), $companies);

        return $this->companies(...array_diff($this->companyList('revokeAccessTo'), $revoked));
    }

    public function templateCompanyAccess(TemplateCompanyAccess $access): self
    {
        return $this->set('office/templatecompanyaccess', $access->value);
    }

    /**
     * Only works when single sign-on is enabled for the organisation.
     */
    public function singleSignOn(SingleSignOn $singleSignOn): self
    {
        return $this->set('account/sso', $singleSignOn->value);
    }

    /**
     * Date the password expires; null for no expiry. Not with single sign-on.
     */
    public function passwordExpires(?DateTimeInterface $date): self
    {
        return $this->set('account/expire', $date === null ? null : XmlValue::date($date));
    }

    /**
     * Disables the account from a moment, until a moment or for good.
     *
     *   $input->disable(new DateTimeImmutable('2026-07-01'), until: new DateTimeImmutable('2026-08-01'));
     */
    public function disable(DateTimeInterface $from, ?DateTimeInterface $until = null): self
    {
        if ($until !== null && $until <= $from) {
            throw new InvalidArgumentException(sprintf(
                'The account must be disabled until after %s, %s given.',
                $from->format('Y-m-d H:i:s'),
                $until->format('Y-m-d H:i:s'),
            ));
        }

        return $this
            ->set('account/disable/from', $from->format('YmdHis'))
            ->set('account/disable/to', $until?->format('YmdHis'));
    }

    /**
     * Removes the period in which the account is disabled.
     */
    public function enable(): self
    {
        return $this->set('account/disable/from', null)->set('account/disable/to', null);
    }

    /**
     * User who authorises the hours of this user; null clears it.
     */
    public function timeAuthoriser(string|UserSummary|User|null $user): self
    {
        $code = $user instanceof UserSummary || $user instanceof User ? $user->code : $user;

        return $this->set('time/authoriser', $code === null ? null : XmlValue::code($code));
    }

    /**
     * Work hours per week, 0 to 168; null clears it.
     */
    public function weekHours(?int $hours): self
    {
        if ($hours !== null && ($hours < 0 || $hours > 168)) {
            throw new InvalidArgumentException(sprintf('Week hours must be 0 to 168, %d given.', $hours));
        }

        return $this->set('time/weekhours', $hours === null ? null : (string) $hours);
    }

    /**
     * Default dimensions for the hour journals of the user. Only the dimensions you pass are
     * sent; pass null to clear one.
     *
     *   $input->expenseDefaults(generalLedgerAccount: '8000', project: 'P0001');
     */
    public function expenseDefaults(
        string|false|null $generalLedgerAccount = false,
        string|false|null $costCenter = false,
        string|false|null $project = false,
        string|false|null $activity = false,
    ): self {
        $input = $this;
        $dimensions = [1 => $generalLedgerAccount, 2 => $costCenter, 3 => $project, 4 => $activity];

        foreach ($dimensions as $number => $code) {
            if ($code !== false) {
                $input = $input->set('expenses/dimension' . $number, $code === null || $code === '' ? null : $code);
            }
        }

        return $input;
    }

    /**
     * Log in through the identity provider of the organisation. Twinfield Support enables this
     * on request.
     *
     * @param string $domain Domain of the identity provider registered in the Wolters Kluwer account.
     * @param EmailAddress|string $email Email address of the user at the identity provider, at most 128 characters.
     */
    public function federation(string $domain, EmailAddress|string $email): self
    {
        if ($domain === '') {
            throw new InvalidArgumentException('Federation domain must not be empty.');
        }

        return $this
            ->set('federation/domain', $domain)
            ->set('federation/email', XmlValue::maxLength('federation email', EmailAddress::of($email)->value, 128));
    }

    /**
     * The `<user>` element to send.
     *
     * @throws LogicException When the settings do not go together, e.g. invite() without role().
     *
     * @internal
     */
    public function toXml(): XmlBuilder
    {
        $this->assertConsistent();

        $xml = XmlBuilder::create('user');
        $xml->add($xml->root(), 'code', $this->code);

        /** @var array<string, DOMElement> $parents */
        $parents = ['' => $xml->root()];

        foreach (self::ELEMENTS as $path) {
            if (array_key_exists($path, $this->values)) {
                $xml->add(self::parent($xml, $parents, dirname($path)), basename($path), $this->values[$path]);
            }

            if ($path === 'office/templatecompanyaccess' && $this->companies !== null) {
                $offices = $xml->add(self::parent($xml, $parents, 'office'), 'offices');

                foreach ($this->companies as $company) {
                    $xml->add($offices, 'office', $company);
                }
            }
        }

        return $xml;
    }

    private function assertConsistent(): void
    {
        $value = fn (string $path): ?string => $this->values[$path] ?? null;

        if ($value('invitationstatus') !== null && ($value('culture') === null || $value('role') === null)) {
            throw new LogicException(sprintf('Inviting user %s needs culture() and role().', $this->code));
        }

        $type = $value('type');

        if ($value('demo') === 'true' && $type !== null && $type !== UserType::AccountantCustomer->value) {
            throw new LogicException(sprintf('User %s: demo() is only for UserType::AccountantCustomer.', $this->code));
        }

        $singleSignOn = $value('account/sso');

        $withSingleSignOn = $singleSignOn !== null && $singleSignOn !== SingleSignOn::Disabled->value;

        if ($value('account/expire') !== null && $withSingleSignOn) {
            throw new LogicException(sprintf(
                'User %s: a password expiry date does not go with single sign-on.',
                $this->code,
            ));
        }
    }

    /**
     * The parent element of a path such as `account/disable`, created when needed.
     *
     * @param array<string, DOMElement> $parents
     */
    private static function parent(XmlBuilder $xml, array &$parents, string $path): DOMElement
    {
        $path = $path === '.' ? '' : $path;

        $parents[$path] ??= $xml->add(self::parent($xml, $parents, dirname($path)), basename($path));

        return $parents[$path];
    }

    /**
     * @return list<string>
     */
    private function companyList(string $method): array
    {
        return $this->companies ?? throw new LogicException(sprintf(
            '%s() needs the company list of user %s: start from edit() of a user with restricted '
            . 'access, or set the full list with companies(). Without a list, the user keeps access to all companies.',
            $method,
            $this->code,
        ));
    }

    private static function companyCode(string|CompanyReference|Office|OfficeSummary $company): string
    {
        $code = is_string($company) ? $company : $company->code;

        if ($code === '') {
            throw new InvalidArgumentException('Company code must not be empty.');
        }

        return $code;
    }

    private static function notNegative(string $field, int $value): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('%s must be 0 or more, %d given.', $field, $value));
        }

        return $value;
    }

    private function set(string $path, ?string $value): self
    {
        return $this->with(['values' => [...$this->values, $path => $value]]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
