<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use SolveBeam\Twinfield\Tests\Support\ExampleRunner;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Tests\Unit\ExamplesTest as ExamplesStructureTest;

/**
 * Runs every example against the live API, so examples never break silently.
 * Read-only: the write guard is active in Example::twinfield(), and examples
 * that change data run in tests/Write/ExamplesTest instead.
 */
final class ExamplesTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        return ExamplesStructureTest::examples();
    }

    #[DataProvider('examples')]
    public function testExampleRuns(string $name): void
    {
        if (in_array($name, ExamplesStructureTest::WRITES, true)) {
            self::markTestSkipped('Changes data; runs via `composer test:write`.');
        }

        // Twinfield limits requests per minute; leave a little room between examples.
        usleep(500_000);

        [$exitCode, $stdout, $stderr] = ExampleRunner::run($name, $this->environmentFor($name));

        self::assertSame(0, $exitCode, "Example {$name} failed:\n{$stderr}");
        self::assertNotSame('', trim($stdout), "Example {$name} printed nothing.");
    }

    /**
     * The authentication example reads everything from env vars, like a real application would.
     *
     * @return array<string, string>
     */
    private function environmentFor(string $name): array
    {
        $environment = array_filter(getenv(), 'is_string');

        if ($name !== 'getting-started/authentication') {
            return $environment;
        }

        $config = $this->environment->config;
        $token = json_decode((string) file_get_contents($config->tokenFile()), true);

        return [
            ...$environment,
            'TWINFIELD_CLIENT_ID' => $config->clientId(),
            'TWINFIELD_CLIENT_SECRET' => $config->clientSecret(),
            'TWINFIELD_REDIRECT_URI' => $config->redirectUri(),
            'TWINFIELD_COMPANY' => $config->company(),
            'TWINFIELD_REFRESH_TOKEN' => is_array($token) ? (string) ($token['refresh_token'] ?? '') : '',
        ];
    }
}
