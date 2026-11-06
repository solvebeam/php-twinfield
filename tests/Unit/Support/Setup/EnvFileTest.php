<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Support\Setup;

use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\Setup\EnvFile;

final class EnvFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/twinfield-envfile-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (scandir($this->directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($this->directory . '/' . $entry);
            }
        }

        @rmdir($this->directory);
    }

    public function testCreatesPrivateFile(): void
    {
        $file = new EnvFile($this->directory . '/.env');
        $file->set(['TWINFIELD_CLIENT_ID' => 'lookup']);

        self::assertSame('0700', substr(sprintf('%o', fileperms($this->directory)), -4));
        self::assertSame('0600', substr(sprintf('%o', fileperms($file->path)), -4));
        self::assertSame(['TWINFIELD_CLIENT_ID' => 'lookup'], Dotenv::parse((string) file_get_contents($file->path)));
    }

    public function testUpdatesKeysAndKeepsOtherLines(): void
    {
        mkdir($this->directory, 0700);
        file_put_contents($this->directory . '/.env', "# My comment\nTWINFIELD_CLIENT_ID=old\nOTHER=keep\n");

        (new EnvFile($this->directory . '/.env'))->set(['TWINFIELD_CLIENT_ID' => 'new', 'TWINFIELD_COMPANY' => '1002']);

        self::assertSame(
            "# My comment\nTWINFIELD_CLIENT_ID=new\nOTHER=keep\nTWINFIELD_COMPANY=1002\n",
            file_get_contents($this->directory . '/.env'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function values(): iterable
    {
        yield 'base64 secret' => ['M69I/Unad0+NwcHdqPl=='];
        yield 'spaces' => ['with spaces'];
        yield 'dollar' => ['pa$word'];
        yield 'single quote' => ["it's \$HOME \"quoted\" \\ back"];
        yield 'url' => ['http://localhost:8765/callback'];
    }

    #[DataProvider('values')]
    public function testValuesSurviveRoundTrip(string $value): void
    {
        $file = new EnvFile($this->directory . '/.env');
        $file->set(['KEY' => $value]);

        self::assertSame(['KEY' => $value], Dotenv::parse((string) file_get_contents($file->path)));
    }
}
