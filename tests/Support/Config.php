<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use Dotenv\Dotenv;
use RuntimeException;
use SolveBeam\Twinfield\Connect\ConnectClient;
use SolveBeam\Twinfield\Connect\Provider\TwinfieldConnect;

/**
 * Configuration for tests and dev scripts.
 *
 * Values are read from, in order of precedence:
 *
 * 1. Real environment variables (e.g. `TWINFIELD_DEBUG=1 composer test:live`).
 * 2. `.env` in the project root (gitignored, optional).
 * 3. `~/.config/twinfield/.env` (or `$TWINFIELD_CONFIG_DIR/.env`).
 *
 * Logging in goes with your own Twinfield client (`TWINFIELD_CLIENT_ID` and `TWINFIELD_CLIENT_SECRET`),
 * or without one through Twinfield Connect (`TWINFIELD_CONNECT_CLIENT_ID` and
 * `TWINFIELD_CONNECT_CLIENT_SECRET`, which `composer twinfield:setup` registers). Your own client
 * wins when both are set.
 */
final readonly class Config
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        public string $configDir,
        private array $values,
    ) {
    }

    public static function load(?string $projectDir = null): self
    {
        $projectDir ??= dirname(__DIR__, 2);
        $configDir = self::env('TWINFIELD_CONFIG_DIR') ?? self::homeDir() . '/.config/twinfield';

        $values = array_merge(
            self::parseFile($configDir . '/.env'),
            self::parseFile($projectDir . '/.env'),
        );

        foreach (array_keys($values) as $key) {
            $value = self::env($key);

            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return new self($configDir, $values);
    }

    public function get(string $key): ?string
    {
        $value = $this->values[$key] ?? self::env($key);

        return $value === null || $value === '' ? null : $value;
    }

    public function require(string $key): string
    {
        return $this->get($key) ?? throw new RuntimeException(sprintf(
            'Missing configuration "%s". Add it to %s/.env.',
            $key,
            $this->configDir,
        ));
    }

    public function flag(string $key): bool
    {
        return in_array(strtolower($this->get($key) ?? ''), ['1', 'true', 'yes', 'on'], true);
    }

    public function hasCredentials(): bool
    {
        return $this->hasOwnClient() || $this->hasConnectClient();
    }

    /**
     * True when logging in goes through Twinfield Connect instead of your own Twinfield client.
     */
    public function usesConnect(): bool
    {
        return !$this->hasOwnClient() && $this->hasConnectClient();
    }

    public function connectClient(): ConnectClient
    {
        return new ConnectClient(
            $this->require('TWINFIELD_CONNECT_CLIENT_ID'),
            $this->require('TWINFIELD_CONNECT_CLIENT_SECRET'),
        );
    }

    /**
     * Base URL of Twinfield Connect; only set `TWINFIELD_CONNECT_URL` for one you run yourself.
     */
    public function connectUrl(): string
    {
        return $this->get('TWINFIELD_CONNECT_URL') ?? TwinfieldConnect::CONNECT_URL;
    }

    public function clientId(): string
    {
        return $this->require('TWINFIELD_CLIENT_ID');
    }

    public function clientSecret(): string
    {
        return $this->require('TWINFIELD_CLIENT_SECRET');
    }

    public function redirectUri(): string
    {
        return $this->get('TWINFIELD_REDIRECT_URI') ?? 'http://localhost:8765/callback';
    }

    public function company(): string
    {
        return $this->require('TWINFIELD_COMPANY');
    }

    /**
     * @return list<string>
     */
    public function writeCompanies(): array
    {
        $companies = array_map(trim(...), explode(',', $this->get('TWINFIELD_WRITE_COMPANIES') ?? ''));

        return array_values(array_filter($companies, static fn (string $code): bool => $code !== ''));
    }

    /**
     * Company code for which writes were confirmed via `composer test:write`.
     */
    public function allowWrites(): ?string
    {
        return $this->get('TWINFIELD_ALLOW_WRITES');
    }

    public function tokenFile(): string
    {
        return $this->configDir . '/token.json';
    }

    /**
     * @return array<string, string>
     */
    private static function parseFile(string $file): array
    {
        if (!is_readable($file)) {
            return [];
        }

        return array_filter(Dotenv::parse((string) file_get_contents($file)), is_string(...));
    }

    private function hasOwnClient(): bool
    {
        return $this->get('TWINFIELD_CLIENT_ID') !== null && $this->get('TWINFIELD_CLIENT_SECRET') !== null;
    }

    private function hasConnectClient(): bool
    {
        return $this->get('TWINFIELD_CONNECT_CLIENT_ID') !== null
            && $this->get('TWINFIELD_CONNECT_CLIENT_SECRET') !== null;
    }

    private static function env(string $key): ?string
    {
        $value = getenv($key);

        return $value === false ? null : $value;
    }

    private static function homeDir(): string
    {
        $home = self::env('HOME') ?? self::env('USERPROFILE');

        if ($home === null) {
            throw new RuntimeException('Cannot determine home directory; set TWINFIELD_CONFIG_DIR.');
        }

        return rtrim($home, '/\\');
    }
}
