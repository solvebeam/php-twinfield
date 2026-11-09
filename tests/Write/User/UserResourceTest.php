<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\User;

use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Updates the user of the access token and restores it.
 *
 * Creating and deleting a user is not tested: Twinfield charges per user, depending on the
 * subscription and the role.
 */
final class UserResourceTest extends WriteTestCase
{
    public function testUpdatesAndRestoresTheCurrentUser(): void
    {
        $users = $this->twinfield->users();
        $original = $users->current();

        // Saving edit() without changes must keep every setting.
        $unchanged = $users->update($original->edit());

        self::assertEquals($original->edit(), $unchanged->edit(), 'An update with edit() changes nothing.');

        $weekHours = $original->time->weekHours === 40 ? 38 : 40;

        try {
            $updated = $users->update($original->edit()->weekHours($weekHours)->shortName('SDK test'));

            self::assertSame($weekHours, $updated->time->weekHours);
            self::assertSame('SDK test', $updated->shortName);
            self::assertSame($original->email, $updated->email, 'edit() keeps other values.');
            self::assertSame($original->companies->codes(), $updated->companies->codes());
        } finally {
            $restored = $users->update($original->edit());
        }

        self::assertSame($original->shortName, $restored->shortName);
        self::assertSame($original->time->weekHours, $restored->time->weekHours);
    }
}
