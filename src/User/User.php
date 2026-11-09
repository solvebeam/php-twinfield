<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A user of the organisation as Twinfield returns it.
 *
 *   $user = $twinfield->users()->get('JDOE');
 *   echo $user->name, ' <', $user->email, '> ', $user->role->name, PHP_EOL;
 *
 *   if ($user->hasAccessTo('1000') && !$user->account->isDisabledAt(new DateTimeImmutable())) { … }
 *
 * To change it, call edit() and pass the result to update(). The password is never returned.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/users/
 */
final readonly class User
{
    public function __construct(
        /** Organisation the user belongs to; the user code is only unique within it. */
        public Organisation $organisation,
        /** User code, e.g. `JDOE`. */
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        public string $email,
        public UserStatus $status,
        /** True for the user of the access token (`@iscurrentuser`). */
        public bool $isCurrentUser,
        public UserType $type,
        public UserCulture $culture,
        public UserRole $role,
        /** True when the user is only used for training. Only for clients of an accountant. */
        public bool $demo,
        /** Twinfield Analysis quota. */
        public int $exchangeQuota,
        /** File Manager quota. */
        public int $fileManagerQuota,
        public InvitationStatus $invitationStatus,
        /** Email address of the Wolters Kluwer account; null when the user is not invited. */
        public ?string $accountEmail,
        public SmsLogin $smsLogin,
        public CompanyAccess $companies,
        public UserAccount $account,
        public TimeSettings $time,
        public ExpenseDefaults $expenseDefaults,
        /** Login through the identity provider of the organisation; null when not enabled. */
        public ?Federation $federation,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        /** Number of times the user was changed. */
        public int $touched,
        /** User who changed this user last. */
        public NamedReference $modifiedBy,
    ) {
        foreach (['code' => $code, 'name' => $name, 'email' => $email] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('User %s must not be empty.', $field));
            }
        }

        if ($exchangeQuota < 0 || $fileManagerQuota < 0 || $touched < 0) {
            throw new InvalidArgumentException(sprintf(
                'User %s must have quotas and touched of 0 or more, got %d, %d and %d.',
                $code,
                $exchangeQuota,
                $fileManagerQuota,
                $touched,
            ));
        }
    }

    /**
     * From a `<user>` element of a read response.
     *
     * @param Organisation $organisation Organisation of the access token: Twinfield leaves it out
     *                                   of the response.
     */
    public static function fromXml(XmlElement $user, Organisation $organisation): self
    {
        $federation = $user->nullableChild('federation');

        try {
            return new self(
                organisation: $organisation,
                code: $user->string('code'),
                name: $user->string('name'),
                // Optional in Twinfield: empty when no short name is set.
                shortName: $user->nullableString('shortname'),
                email: $user->string('email'),
                status: $user->enumAttribute('status', UserStatus::class),
                isCurrentUser: $user->boolAttribute('iscurrentuser'),
                type: $user->enum('type', UserType::class),
                culture: $user->enum('culture', UserCulture::class),
                role: UserRole::fromXml($user->child('role')),
                demo: $user->bool('demo'),
                exchangeQuota: $user->int('exchangequota'),
                fileManagerQuota: $user->int('filemanagerquota'),
                invitationStatus: $user->enum('invitationstatus', InvitationStatus::class),
                // Not documented; empty for users that are not invited.
                accountEmail: $user->nullableString('wkemailaddress'),
                smsLogin: SmsLogin::fromXml($user->child('smslogin')),
                companies: CompanyAccess::fromXml($user->child('office'), $organisation),
                account: UserAccount::fromXml($user->child('account')),
                time: TimeSettings::fromXml($user->child('time')),
                expenseDefaults: ExpenseDefaults::fromXml($user->child('expenses')),
                // Only there when Twinfield Support enabled federation for the organisation.
                federation: $federation === null ? null : Federation::fromXml($federation),
                created: $user->dateTime('created'),
                modified: $user->dateTime('modified'),
                touched: $user->int('touched'),
                modifiedBy: NamedReference::fromXml($user->child('user')),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $user->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * True when the user may open the company: always for a user with access to all companies.
     */
    public function hasAccessTo(string|CompanyReference|Office|OfficeSummary $company): bool
    {
        return $this->companies->hasAccessTo($company);
    }

    /**
     * Input with the settings of this user, to change and pass to update() or save().
     *
     *   $users->update($user->edit()->email('j.doe@example.com')->weekHours(32));
     *
     * Left out, so they stay as they are: the password, the invitation status (which Twinfield
     * locks), `demo` for users that are no client of an accountant, the password expiry with
     * single sign-on, and the company list of users with access to all companies.
     */
    public function edit(): UserInput
    {
        $input = UserInput::create($this->code, $this->name)
            ->shortName($this->shortName)
            ->email($this->email)
            ->type($this->type)
            ->culture($this->culture)
            ->role($this->role)
            ->exchangeQuota($this->exchangeQuota)
            ->fileManagerQuota($this->fileManagerQuota)
            ->smsLogin($this->smsLogin->phoneNumber, $this->smsLogin->enabled)
            ->defaultCompany($this->companies->defaultCompany?->code)
            ->templateCompanyAccess($this->companies->templateCompanyAccess)
            ->singleSignOn($this->account->singleSignOn)
            ->timeAuthoriser($this->time->authoriser?->code)
            ->weekHours($this->time->weekHours)
            ->expenseDefaults(
                generalLedgerAccount: $this->expenseDefaults->generalLedgerAccount?->code,
                costCenter: $this->expenseDefaults->costCenter?->code,
                project: $this->expenseDefaults->project?->code,
                activity: $this->expenseDefaults->activity?->code,
            );

        $input = $this->account->disabledFrom === null
            ? $input->enable()
            : $input->disable($this->account->disabledFrom, $this->account->disabledUntil);

        if ($this->type === UserType::AccountantCustomer) {
            $input = $input->demo($this->demo);
        }

        if ($this->account->singleSignOn === SingleSignOn::Disabled) {
            $input = $input->passwordExpires($this->account->passwordExpires);
        }

        if ($this->companies->restricted && $this->companies->companies !== []) {
            $input = $input->companies(...$this->companies->codes());
        }

        if ($this->federation !== null) {
            $input = $input->federation($this->federation->domain, $this->federation->email);
        }

        return $input;
    }
}
