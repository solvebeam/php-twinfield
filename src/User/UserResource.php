<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Users of the organisation: who may log in, with which role, and to which companies.
 *
 *   $users = $twinfield->company('1000')->users();
 *
 *   foreach ($users->list() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $me = $users->current();                  // the user of the access token
 *   $user = $users->get('JDOE');
 *
 *   $users->update($user->edit()->weekHours(32)->grantAccessTo('1001'));
 *   $users->delete('JDOE', UserDeleteReason::NoLongerOperative, 'Left the company');
 *
 * Users belong to the organisation, not to a company, but Twinfield needs a company in the
 * request: any company of the client works.
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/users/
 */
final readonly class UserResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * All users, over all Finder pages.
     *
     * @return list<UserSummary>
     */
    public function list(): array
    {
        return iterator_to_array($this->search()->all(), false);
    }

    /**
     * Search users by code and name, e.g. `search('*AI')->inCode()->get()`. Also see
     * `withMutualCompanies()` and `withoutAccessRules()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<UserSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $this->assertCompany();

        $query = $this->twinfield->finder(FinderType::Users);

        return CodeNameSearch::forOrganisation($query, UserSummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * The user with all settings.
     *
     * @throws UserNotFoundException When there is no user with this code.
     */
    public function get(string $code): User
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'user');
        $xml->add($xml->root(), 'code', $code);

        return User::fromXml(XmlElement::root($this->send($xml, $code)), $this->twinfield->organisation());
    }

    /**
     * The user, or null when there is no user with this code.
     */
    public function find(string $code): ?User
    {
        try {
            return $this->get($code);
        } catch (UserNotFoundException) {
            return null;
        }
    }

    /**
     * The user of the access token: who the client works as.
     *
     *   echo 'Logged in as ', $twinfield->users()->current()->name, PHP_EOL;
     *
     * The code comes from the access token provider, see `Twinfield::userCode()`.
     */
    public function current(): User
    {
        return $this->get($this->twinfield->userCode());
    }

    /**
     * Creates a new user. Reads first, so an existing user is never overwritten.
     *
     * @throws UserAlreadyExistsException When the code is already in use.
     */
    public function create(UserInput $user): User
    {
        if ($this->find($user->code) !== null) {
            throw new UserAlreadyExistsException($user->code);
        }

        return $this->save($user);
    }

    /**
     * Updates an existing user with the values of the input. Start from `$user->edit()` to keep
     * the values you do not change.
     *
     * @throws UserNotFoundException When there is no user with this code.
     */
    public function update(UserInput $user): User
    {
        $this->get($user->code);

        return $this->save($user);
    }

    /**
     * Creates the user, or updates it when the code exists (no existence check). Reads it back
     * afterwards, so you always get the complete user.
     *
     * @throws ProcessXmlException When Twinfield refuses the user.
     */
    public function save(UserInput $user): User
    {
        $this->assertCompany();

        // Saving creates a missing user, so errors are not mapped to UserNotFoundException.
        $this->twinfield->processXml($user->toXml()->toXml());

        return $this->get($user->code);
    }

    /**
     * Deletes the user, optionally with the reason. When the user is used in transactions,
     * Twinfield hides it instead.
     *
     * @param ?string $comment Why the user is deleted, at most 1000 characters.
     *
     * @throws UserNotFoundException When there is no user with this code.
     */
    public function delete(string $code, ?UserDeleteReason $reason = null, ?string $comment = null): DeleteResult
    {
        if ($comment !== null && $reason === null) {
            throw new InvalidArgumentException('A delete comment needs a reason.');
        }

        $status = $this->changeStatus($code, UserStatus::Deleted, $reason, $comment);

        return match ($status) {
            UserStatus::Deleted => DeleteResult::Deleted,
            UserStatus::Hidden => DeleteResult::Hidden,
            UserStatus::Active => throw new UnexpectedResponseException(sprintf(
                'User %s is still active after deleting it.',
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden user active again.
     *
     * @throws UserNotFoundException When there is no user with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, UserStatus::Active);
    }

    private function changeStatus(
        string $code,
        UserStatus $status,
        ?UserDeleteReason $reason = null,
        ?string $comment = null,
    ): UserStatus {
        $xml = XmlBuilder::create('user', ['status' => $status->value]);
        $xml->add($xml->root(), 'code', $code);

        if ($reason !== null) {
            $deleteReason = $xml->add($xml->root(), 'deletereason');
            $xml->add($deleteReason, 'code', $reason->value);

            if ($comment !== null && $comment !== '') {
                $xml->add($deleteReason, 'comment', XmlValue::maxLength('delete comment', $comment, 1000));
            }
        }

        return XmlElement::root($this->send($xml, $code))->enumAttribute('status', UserStatus::class);
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        $this->assertCompany();

        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw UserNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        }
    }

    /**
     * Without company, Twinfield refuses the request (error 359).
     */
    private function assertCompany(): void
    {
        if ($this->twinfield->companyCode() === null) {
            throw new LogicException(
                'Twinfield needs a company for user requests, although users belong to the organisation: '
                . 'use company() first, any company works.',
            );
        }
    }
}
