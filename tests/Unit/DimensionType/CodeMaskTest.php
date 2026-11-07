<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\DimensionType;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionType\CodeMask;

final class CodeMaskTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function codes(): iterable
    {
        yield 'customer' => ['1[0-9][0-9][0-9]', '1010', true];
        yield 'customer too long' => ['1[0-9][0-9][0-9]', '10100', false];
        yield 'customer wrong first digit' => ['1[0-9][0-9][0-9]', '2000', false];
        yield 'project' => ['P[0-9][0-9][0-9][0-9]', 'P0001', true];
        yield 'project lower case' => ['P[0-9][0-9][0-9][0-9]', 'p0001', false];
        yield 'any' => ['*', 'ANYTHING-1', true];
        yield 'prefix and any' => ['A*', 'A001', true];
        yield 'prefix and any, other prefix' => ['A*', 'B001', false];
        yield 'documented # digit' => ['1####', '12345', true];
        yield 'documented # not a letter' => ['1####', '1234A', false];
        yield 'question mark' => ['K?', 'K1', true];
        yield 'letter set' => ['[ABC]1', 'B1', true];
        yield 'letter set, other letter' => ['[ABC]1', 'D1', false];
        yield 'letter range' => ['[A-Z][0-9]', 'Q7', true];
        yield 'literal plus and dot' => ['1+.', '1+.', true];
        yield 'literal plus is no quantifier' => ['1+', '11', false];
        yield 'unclosed bracket is literal' => ['[1', '[1', true];
        yield 'trailing dash in set is literal' => ['[A-]', '-', true];
        yield 'no newline at end' => ['1', "1\n", false];
    }

    #[DataProvider('codes')]
    public function testMatchesCodes(string $mask, string $code, bool $matches): void
    {
        self::assertSame($matches, (new CodeMask($mask))->matches($code));
    }

    public function testAllowsAnyCode(): void
    {
        self::assertTrue(CodeMask::any()->allowsAnyCode());
        self::assertFalse((new CodeMask('1*'))->allowsAnyCode());
        self::assertSame('*', (string) CodeMask::any());
    }

    public function testOfAcceptsAStringOrAMask(): void
    {
        $mask = new CodeMask('A*');

        self::assertSame($mask, CodeMask::of($mask));
        self::assertEquals($mask, CodeMask::of('A*'));
    }

    public function testRejectsAnEmptyMask(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CodeMask('');
    }

    public function testRejectsATooLongMask(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CodeMask(str_repeat('#', 81));
    }
}
