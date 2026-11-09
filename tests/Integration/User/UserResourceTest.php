<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\User;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\User\UserNotFoundException;

/**
 * Live user searches and reads (read-only).
 */
final class UserResourceTest extends IntegrationTestCase
{
    public function testReadsTheCurrentUser(): void
    {
        $user = $this->twinfield->users()->current();

        self::assertSame($this->twinfield->userCode(), $user->code);
        self::assertTrue($user->isCurrentUser, 'Twinfield marks the user of the access token.');
    }

    public function testSearchesAndReadsAllUsers(): void
    {
        $users = $this->twinfield->users();
        $list = $users->list();

        self::assertNotEmpty($list);
        self::assertSame(count($list), $users->search()->count());
        self::assertGreaterThanOrEqual(count($list), $users->search()->withMutualCompanies()->count());

        foreach ($list as $summary) {
            usleep(300_000);
            $user = $users->get($summary->code);

            self::assertSame($summary->code, $user->code);
            self::assertSame($summary->name, $user->name);
            self::assertSame($user->code === $this->twinfield->userCode(), $user->isCurrentUser);
            self::assertSame($user->companies->restricted, $user->companies->companies !== [], 'Restricted users list their companies.');

            // edit() must give an input that Twinfield accepts; here only that it builds.
            self::assertStringStartsWith('<user><code>' . $user->code . '</code>', $user->edit()->toXml()->toXml());
        }
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->twinfield->users()->get('SDKMISSING9');
    }
}
