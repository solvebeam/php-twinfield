<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Dimension;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ChangeModeTest extends TestCase
{
    public function testMapsToLockedAndInherit(): void
    {
        self::assertSame([false, false], [ChangeMode::Allow->locked(), ChangeMode::Allow->inherit()]);
        self::assertSame([true, false], [ChangeMode::Disallow->locked(), ChangeMode::Disallow->inherit()]);
        self::assertSame([true, true], [ChangeMode::Inherit->locked(), ChangeMode::Inherit->inherit()]);
    }

    public function testReadsEveryModeBack(): void
    {
        foreach (ChangeMode::cases() as $mode) {
            self::assertSame($mode, ChangeMode::fromXml($mode->locked(), $mode->inherit(), 'rate'));
        }
    }

    public function testRejectsInheritWithoutLocked(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('rate has inherit="true" with locked="false", which is not a valid change mode.');

        ChangeMode::fromXml(false, true, 'rate');
    }
}
