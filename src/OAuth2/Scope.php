<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2;

/**
 * OAuth 2.0 scopes of Twinfield.
 *
 * The identity scopes (`openid`, `twf.*`) are needed for every API. The REST API also needs a scope
 * per operation, e.g. `readVat` for the VAT codes: the Swagger description of each operation lists
 * them ("The authorization scopes required for this operation are: …"). Without it the REST API
 * answers HTTP 403. Your OAuth client must be allowed to request a scope.
 *
 *   $provider->getAuthorizationUrl(['scope' => Scope::values(...Scope::identity(), Scope::ReadVat)]);
 *
 * @see https://developers.twinfield.com/documentation/api/authentication/openid-connect-auth
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index
 */
enum Scope: string
{
    /** Standard OpenID Connect scope, needed with `twf.user`. */
    case OpenId = 'openid';

    /** Refresh tokens in the authorization code flow. */
    case OfflineAccess = 'offline_access';

    /** Twinfield ID and user code (identity token). */
    case User = 'twf.user';

    /** Organisation code, ID and cluster URL. */
    case Organisation = 'twf.organisation';

    /** User code in the organisation: mandatory to log in. */
    case OrganisationUser = 'twf.organisationUser';

    /** REST: read sales articles. */
    case ReadArticles = 'readArticles';

    /** REST: create, update and delete sales articles (also reads them). */
    case WriteArticles = 'writeArticles';

    /** REST: PSD2 bank feeds. */
    case ReadBankAccounts = 'readBankAccounts';

    /** REST: ledger accounts. */
    case ReadBookkeeping = 'readBookkeeping';

    /** REST: matching audit trail. */
    case ReadBookkeepingMatches = 'readBookkeepingMatches';

    /** REST: budget lines, annual budgets and budget periods. */
    case ReadBudgets = 'readBudgets';

    /** REST: change budget lines, import budgets (also reads them). */
    case WriteBudgets = 'writeBudgets';

    /** REST: budget scenarios and spreading templates. */
    case ReadBudgetScenarios = 'readBudgetScenarios';

    /** REST: create, update and delete budget scenarios and spreading templates. */
    case WriteBudgetScenarios = 'writeBudgetScenarios';

    /** REST: customer changes, posting rules and ledger accounts. */
    case ReadCustomers = 'readCustomers';

    /** REST: fixed assets of the new fixed assets module, with classes, groups and reports. */
    case ReadFixedAssets = 'readFixedAssets';

    /** REST: supplier changes, posting rules and ledger accounts. */
    case ReadSuppliers = 'readSuppliers';

    /** REST: VAT codes. */
    case ReadVat = 'readVat';

    /** REST: VAT groups; also gives access to the ledger accounts. */
    case ReadVatGroup = 'readVatGroup';

    /**
     * The scopes to identify the user and keep the session: `openid`, `twf.user`, `twf.organisation`,
     * `twf.organisationUser` and `offline_access`. Enough for the SOAP services.
     *
     * @return list<self>
     */
    public static function identity(): array
    {
        return [self::OpenId, self::User, self::Organisation, self::OrganisationUser, self::OfflineAccess];
    }

    /**
     * All REST API scopes, read and write.
     *
     * @return list<self>
     */
    public static function rest(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $scope): bool => !in_array($scope, self::identity(), true),
        ));
    }

    /**
     * The values to request, e.g. for `getAuthorizationUrl(['scope' => …])`.
     *
     * @return list<string>
     */
    public static function values(self ...$scopes): array
    {
        return array_values(array_unique(array_map(static fn (self $scope): string => $scope->value, $scopes)));
    }
}
