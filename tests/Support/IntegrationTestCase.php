<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\OAuth2\Scope;
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
                'No Twinfield credentials. Run `composer twinfield:setup`, or add them to %s/.env.',
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
     * Skips the test when the access token lacks a scope the REST API needs for it, e.g.
     * `requireScopes(Scope::ReadVat)`.
     */
    protected function requireScopes(Scope ...$scopes): void
    {
        $missing = array_diff(Scope::values(...$scopes), $this->environment->session->scopes());

        if ($missing !== []) {
            self::markTestSkipped(sprintf(
                'The access token has no scope %s. Log in with the REST API scopes:'
                . ' `composer twinfield:login -- --rest`.',
                implode(', ', $missing),
            ));
        }
    }

    /**
     * Fixture name used when recording with TWINFIELD_RECORD=1.
     */
    protected function recordLabel(): string
    {
        return Http\RecordingClient::labelFor(static::class, $this->name());
    }
}
