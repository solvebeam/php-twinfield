<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support\Setup;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Setup\Prompt;
use SolveBeam\Twinfield\Tests\Support\Setup\Setup;
use SolveBeam\Twinfield\Tests\Support\Setup\SetupException;

final class PromptTest extends TestCase
{
    public function testEnterAcceptsDefault(): void
    {
        self::assertSame(
            'http://localhost:8765/callback',
            $this->prompt("\n")->ask('Redirect URI', 'http://localhost:8765/callback'),
        );
    }

    public function testRequiredQuestionAsksAgain(): void
    {
        self::assertSame('lookup', $this->prompt("\n\nlookup\n")->ask('Client ID'));
    }

    public function testSecretCanKeepCurrentValue(): void
    {
        self::assertSame('', $this->prompt("\n")->askSecret('Client secret', keepCurrent: true));
        self::assertSame('s3cret', $this->prompt("\ns3cret\n")->askSecret('Client secret'));
    }

    public function testEndOfInputCancels(): void
    {
        $this->expectException(SetupException::class);

        $this->prompt('')->ask('Client ID');
    }

    public function testSetupDoesNotAskWithoutTerminal(): void
    {
        $prompt = new Prompt($this->stream("lookup\n"), $this->stream(''), interactive: false);

        $this->expectException(SetupException::class);
        $this->expectExceptionMessage('TWINFIELD_CLIENT_ID=');

        (new Setup($prompt))->ensure(new Config(sys_get_temp_dir() . '/twinfield-none', []));
    }

    private function prompt(string $input): Prompt
    {
        return new Prompt($this->stream($input), $this->stream(''), interactive: true);
    }

    /**
     * @return resource
     */
    private function stream(string $contents): mixed
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
