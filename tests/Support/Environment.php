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

        return $company === null ? $twinfield : $twinfield->company($company, self::companyId($twinfield, $company));
    }

    /**
     * GUID of the company for the REST API, looked up once per process: every test gets a new client,
     * and the Finder lookup of all offices would hit the rate limit.
     */
    private static function companyId(Twinfield $twinfield, string $company): ?string
    {
        /** @var ?array<string, string> $ids */
        static $ids = null;

        if ($ids === null) {
            $ids = [];

            foreach ($twinfield->offices()->list() as $office) {
                $ids[$office->code] = $office->companyId;
            }
        }

        return $ids[$company] ?? null;
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

        // REST writes name the company by GUID; the Finder (a read) gives its code.
        $companyCodeOf = function (string $companyId) use ($client): ?string {
            $psr17 = new Psr17Factory();

            foreach (Twinfield::create($this->session, $client, $psr17, $psr17)->offices()->list() as $office) {
                if ($office->companyId === strtolower($companyId)) {
                    return $office->code;
                }
            }

            return null;
        };

        return new WriteGuardClient(
            $client,
            $this->config->writeCompanies(),
            $this->config->allowWrites(),
            $companyCodeOf,
        );
    }
}
