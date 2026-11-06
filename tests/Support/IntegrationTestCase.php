<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Twinfield;

/**
 * Base class for tests against the live Twinfield API.
 *
 * Tests are skipped (not failed) when there are no credentials or no
 * refresh token yet, with a hint on how to set them up.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected Environment $environment;

    protected Twinfield $twinfield;

    protected function setUp(): void
    {
        $config = Config::load();

        if (!$config->hasCredentials()) {
            self::markTestSkipped(sprintf(
                'No Twinfield credentials. Add TWINFIELD_CLIENT_ID and TWINFIELD_CLIENT_SECRET to %s/.env.',
                $config->configDir,
            ));
        }

        $this->environment = Environment::fromConfig($config, $this->recordLabel());

        if (!$this->environment->session->hasRefreshToken()) {
            self::markTestSkipped('No Twinfield refresh token. Run `composer twinfield:login` first.');
        }

        $this->twinfield = $this->environment->client();
    }

    /**
     * Fixture name used when recording with TWINFIELD_RECORD=1.
     */
    protected function recordLabel(): string
    {
        return Http\RecordingClient::labelFor(static::class, $this->name());
    }
}
