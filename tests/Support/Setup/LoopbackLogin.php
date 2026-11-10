<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Setup;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Session;

/**
 * Browser login for CLI tools: opens the Twinfield login, catches the
 * redirect on localhost and stores the tokens via Session.
 */
final readonly class LoopbackLogin
{
    public const array SCOPES = ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access'];

    /** The identity scopes plus all REST API scopes (`--rest`). */
    public const array REST_SCOPES = [
        ...self::SCOPES,
        'readArticles', 'writeArticles', 'readBankAccounts', 'readBookkeeping', 'readBookkeepingMatches',
        'readBudgets', 'writeBudgets', 'readBudgetScenarios', 'writeBudgetScenarios', 'readCustomers',
        'readFixedAssets', 'readSuppliers', 'readVat', 'readVatGroup',
    ];

    private const int TIMEOUT = 300;

    public function __construct(
        private Config $config,
        private Prompt $prompt,
    ) {
    }

    /**
     * @param list<string> $scopes
     */
    public function run(bool $manual = false, array $scopes = self::SCOPES): AccessTokenValidation
    {
        $session = new Session($this->config);
        $provider = $session->provider();

        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => $scopes,
            // OpenID Connect replay protection; Twinfield lists it as an authorize parameter.
            'nonce' => bin2hex(random_bytes(16)),
        ]);
        $state = $provider->getState();

        $this->prompt->line(
            sprintf('[1/4] Client "%s", redirect URI %s', $this->config->clientId(), $this->config->redirectUri()),
        );
        $this->prompt->line('      Scopes: ' . implode(' ', $scopes));
        $this->prompt->line('[2/4] Opening the Twinfield login in your browser…');
        $this->prompt->line('      If it does not open, visit: ' . $authorizationUrl);

        self::openBrowser($authorizationUrl);

        $query = $manual ? $this->askForRedirect() : $this->waitForRedirect($this->config->redirectUri());

        if (isset($query['error'])) {
            throw new SetupException(sprintf(
                "Twinfield returned \"%s\": %s\n%s",
                $query['error'],
                $query['error_description'] ?? 'no description',
                $this->hint(),
            ));
        }

        if (!hash_equals($state, $query['state'] ?? '')) {
            throw new SetupException('State mismatch: the redirect does not belong to this login attempt. Try again.');
        }

        if (!isset($query['code'])) {
            throw new SetupException('The redirect does not contain an authorization code.');
        }

        $this->prompt->line('[3/4] Exchanging the authorization code for tokens…');

        try {
            $token = $provider->getAccessToken('authorization_code', ['code' => $query['code']]);
            $validation = $provider->getAccessTokenValidation($token);
        } catch (IdentityProviderException $exception) {
            throw new SetupException(
                'Token request failed: ' . $exception->getMessage() . "\n" . $this->hint(),
                0,
                $exception,
            );
        }

        if ($token->getRefreshToken() === null) {
            throw new SetupException(
                'Twinfield did not return a refresh token. Check that the client may use the "offline_access" scope.',
            );
        }

        $session->store($token, $validation);

        $this->prompt->line('[4/4] Saved ' . $this->config->tokenFile());
        $this->prompt->line();
        $this->prompt->line('Logged in to Twinfield.');
        $this->prompt->line('  Organisation:  ' . $validation->organisation->code);
        $this->prompt->line('  User:          ' . $validation->organisationUserCode);
        $this->prompt->line('  Cluster:       ' . $validation->clusterUrl);
        $this->prompt->line();

        return $validation;
    }

    /**
     * Hint for the errors we have seen in practice.
     */
    public function hint(): string
    {
        return sprintf(
            "Check that client ID \"%s\" is correct and that the redirect URI is registered exactly as %s\n"
            . "(scheme, host, port and path; no trailing slash). Change them with: composer twinfield:setup",
            $this->config->clientId(),
            $this->config->redirectUri(),
        );
    }

    private static function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            exec('open ' . escapeshellarg($url));
        } elseif (PHP_OS_FAMILY === 'Linux') {
            exec('xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &');
        } elseif (PHP_OS_FAMILY === 'Windows') {
            exec('start "" ' . escapeshellarg($url));
        }
    }

    /**
     * Waits for the OAuth redirect on the redirect URI and returns its query parameters.
     *
     * @return array<array-key, string>
     */
    private function waitForRedirect(string $redirectUri): array
    {
        $parts = parse_url($redirectUri);
        $port = $parts['port'] ?? 80;
        $path = $parts['path'] ?? '/';
        $errorMessage = null;

        // Browsers may resolve "localhost" to IPv4 or IPv6, so listen on both.
        $servers = [];

        foreach (['127.0.0.1', '[::1]'] as $host) {
            $server = @stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errorCode, $errorMessage);

            if ($server !== false) {
                $servers[] = $server;
            }
        }

        if ($servers === []) {
            throw new SetupException(sprintf(
                'Cannot listen on port %d (%s). Try: composer twinfield:login -- --manual',
                $port,
                $errorMessage ?? 'unknown',
            ));
        }

        $this->prompt->line(
            sprintf('      Waiting for the redirect on %s (max %d seconds)…', $redirectUri, self::TIMEOUT),
        );

        $deadline = time() + self::TIMEOUT;

        while (time() < $deadline) {
            $read = $servers;
            $write = $except = null;

            if (stream_select($read, $write, $except, 1) < 1) {
                continue;
            }

            // stream_select() keeps the array keys, so the ready socket is not always at index 0.
            $connection = stream_socket_accept(reset($read), 1);

            if ($connection === false) {
                continue;
            }

            $requestLine = (string) fgets($connection);
            $target = explode(' ', $requestLine)[1] ?? '/';

            if (parse_url($target, PHP_URL_PATH) !== $path) {
                fwrite($connection, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                fclose($connection);

                continue;
            }

            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

            $html = isset($query['code'])
                ? '<h1>Twinfield login received</h1><p>You can close this tab and return to the terminal.</p>'
                : '<h1>Twinfield login failed</h1><p>See the terminal for details.</p>';

            fwrite($connection, sprintf(
                "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\n"
                    . "Content-Length: %d\r\nConnection: close\r\n\r\n%s",
                strlen($html),
                $html,
            ));
            fclose($connection);

            return array_map(strval(...), array_filter($query, is_string(...)));
        }

        throw new SetupException('Timed out waiting for the redirect. ' . $this->hint());
    }

    /**
     * @return array<array-key, string>
     */
    private function askForRedirect(): array
    {
        $this->prompt->line(
            'After logging in, your browser is sent to the redirect URI (the page may fail to load, that is fine).',
        );

        $url = $this->prompt->ask('Paste the full URL from the address bar');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return array_map(strval(...), array_filter($query, is_string(...)));
    }
}
