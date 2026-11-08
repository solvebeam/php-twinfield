<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support;

use League\OAuth2\Client\Token\AccessTokenInterface;
use RuntimeException;
use SolveBeam\Twinfield\Authentication\AccessTokenProvider;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

/**
 * Keeps a valid Twinfield access token around for tests and dev scripts.
 *
 * The refresh token from `composer twinfield:login` is stored in
 * `~/.config/twinfield/token.json`. When the access token is (almost)
 * expired, it is refreshed automatically and the file is updated.
 */
final class Session implements AccessTokenProvider
{
    /** Refresh this many seconds before the access token expires. */
    private const EXPIRY_MARGIN = 120;

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    private static ?self $shared = null;

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public static function shared(Config $config): self
    {
        return self::$shared ??= new self($config);
    }

    public function provider(): Twinfield
    {
        return new Twinfield([
            'clientId' => $this->config->clientId(),
            'clientSecret' => $this->config->clientSecret(),
            'redirectUri' => $this->config->redirectUri(),
        ]);
    }

    public function hasRefreshToken(): bool
    {
        return is_string($this->load()['refresh_token'] ?? null);
    }

    public function accessToken(): string
    {
        $data = $this->load();
        $expires = (int) ($data['expires'] ?? 0);

        if (!is_string($data['access_token'] ?? null) || $expires - self::EXPIRY_MARGIN <= time()) {
            $data = $this->refresh();
        }

        return (string) $data['access_token'];
    }

    public function clusterUrl(): string
    {
        $this->accessToken();

        return rtrim((string) ($this->load()['cluster_url'] ?? ''), '/');
    }

    public function userCode(): string
    {
        $this->accessToken();
        $userCode = $this->load()['organisation_user_code'] ?? null;

        if (!is_string($userCode) || $userCode === '') {
            throw new RuntimeException('No user code in the Twinfield session. Run `composer twinfield:login` again.');
        }

        return $userCode;
    }

    public function organisationCode(): string
    {
        return (string) ($this->load()['organisation_code'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $refreshToken = $this->load()['refresh_token'] ?? null;

        if (!is_string($refreshToken)) {
            throw new RuntimeException('No Twinfield refresh token found. Run `composer twinfield:login` first.');
        }

        $provider = $this->provider();
        $token = $provider->getAccessToken('refresh_token', ['refresh_token' => $refreshToken]);

        return $this->store($token, $provider->getAccessTokenValidation($token), $refreshToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function store(
        AccessTokenInterface $token,
        AccessTokenValidation $validation,
        ?string $previousRefreshToken = null,
    ): array {
        $data = [
            'access_token' => $token->getToken(),
            // Twinfield does not always return a new refresh token; keep the old one then.
            'refresh_token' => $token->getRefreshToken() ?? $previousRefreshToken,
            'expires' => $token->getExpires() ?? $validation->exp,
            'cluster_url' => $validation->clusterUrl,
            'organisation_code' => $validation->organisationCode,
            'organisation_user_code' => $validation->organisationUserCode,
            'updated_at' => date(DATE_ATOM),
        ];

        $this->write($data);
        $this->data = $data;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $file = $this->config->tokenFile();

        if (!is_readable($file)) {
            return $this->data = [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return $this->data = is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(array $data): void
    {
        $file = $this->config->tokenFile();
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }

        // Write to a temporary file first so a crash never leaves a broken token file.
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        chmod($tmp, 0600);
        rename($tmp, $file);
    }
}
