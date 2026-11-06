<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use SolveBeam\Twinfield\Tests\Support\Http\DebugClient;
use SolveBeam\Twinfield\Tests\Support\Http\RecordingClient;
use SolveBeam\Twinfield\Tests\Support\Http\WriteGuardClient;
use SolveBeam\Twinfield\Twinfield;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Live Twinfield client for tests, examples and `bin/` scripts.
 *
 *   $twinfield = Environment::fromConfig()->client();
 *
 * HTTP stack: write guard → recorder (TWINFIELD_RECORD=1) → debug output (TWINFIELD_DEBUG=1) → Symfony.
 */
final readonly class Environment
{
    public function __construct(
        public Config $config,
        public Session $session,
        private ?string $recordLabel = null,
    ) {
    }

    public static function fromConfig(?Config $config = null, ?string $recordLabel = null): self
    {
        $config ??= Config::load();

        return new self($config, Session::shared($config), $recordLabel);
    }

    /**
     * Client for TWINFIELD_COMPANY, or without company when it is not set.
     */
    public function client(): Twinfield
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create($this->session, $this->httpClient(), $psr17, $psr17);
        $company = $this->config->get('TWINFIELD_COMPANY');

        return $company === null ? $twinfield : $twinfield->company($company);
    }

    public function httpClient(): ClientInterface
    {
        $client = new Psr18Client();

        if ($this->config->flag('TWINFIELD_DEBUG')) {
            $client = new DebugClient($client);
        }

        if ($this->config->flag('TWINFIELD_RECORD')) {
            $client = new RecordingClient(
                $client,
                dirname(__DIR__) . '/Fixtures/raw',
                $this->recordLabel ?? 'recording-' . date('Ymd-His'),
            );
        }

        return new WriteGuardClient($client, $this->config->writeCompanies(), $this->config->allowWrites());
    }
}
