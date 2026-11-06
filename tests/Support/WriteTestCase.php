<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

/**
 * Base class for tests that create or change data in Twinfield.
 *
 * Put these tests in tests/Write. They only run via `composer test:write`,
 * which asks you to confirm the company code. Use `$this->company()` as the company in these tests.
 */
abstract class WriteTestCase extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->environment->config;

        if ($config->allowWrites() === null || $config->allowWrites() !== $config->company()) {
            self::markTestSkipped('Write tests only run via `composer test:write`.');
        }
    }

    protected function company(): string
    {
        return $this->environment->config->company();
    }
}
