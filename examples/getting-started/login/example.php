<?php

declare(strict_types=1);

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield as TwinfieldProvider;

require __DIR__ . '/../../../vendor/autoload.php';

function requireEnv(string $name): string
{
    return getenv($name) ?: throw new RuntimeException("Set the environment variable {$name}.");
}

function fail(string $message): never
{
    http_response_code(400);
    echo htmlspecialchars($message), PHP_EOL;
    exit;
}

// From the command line, start PHP's built-in web server on the host of the redirect URI.
if (PHP_SAPI === 'cli') {
    $redirect = parse_url(requireEnv('TWINFIELD_REDIRECT_URI'));
    $host = ($redirect['host'] ?? 'localhost') . ':' . ($redirect['port'] ?? 80);

    echo "Open http://{$host}/ to log in. Stop with Ctrl+C.", PHP_EOL;
    passthru(sprintf('%s -S %s %s', escapeshellarg(PHP_BINARY), escapeshellarg($host), escapeshellarg(__FILE__)));
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
session_start();

$provider = new TwinfieldProvider([
    'clientId' => requireEnv('TWINFIELD_CLIENT_ID'),
    'clientSecret' => requireEnv('TWINFIELD_CLIENT_SECRET'),
    'redirectUri' => requireEnv('TWINFIELD_REDIRECT_URI'),
]);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 1. Send the user to the Twinfield login.
if ($path === '/') {
    $url = $provider->getAuthorizationUrl([
        'scope' => ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access'],
        'nonce' => bin2hex(random_bytes(16)),
    ]);

    // The provider generated a random state; check it in the callback.
    $_SESSION['twinfield_state'] = $provider->getState();

    header('Location: ' . $url);
    exit;
}

if ($path !== parse_url(requireEnv('TWINFIELD_REDIRECT_URI'), PHP_URL_PATH)) {
    http_response_code(404);
    exit;
}

// 2. Twinfield redirects back with an error, or with a code and the state.
if (isset($_GET['error'])) {
    fail("Twinfield returned {$_GET['error']}: " . ($_GET['error_description'] ?? 'no description'));
}

$state = $_SESSION['twinfield_state'] ?? '';
unset($_SESSION['twinfield_state']);

if ($state === '' || !hash_equals($state, (string) ($_GET['state'] ?? ''))) {
    fail('State mismatch: this redirect does not belong to this login. Start again at /.');
}

// 3. Exchange the code (valid for 5 minutes) for tokens.
try {
    $token = $provider->getAccessToken('authorization_code', ['code' => (string) ($_GET['code'] ?? '')]);
    $validation = $provider->getAccessTokenValidation($token);
} catch (IdentityProviderException $exception) {
    fail('Token request failed: ' . $exception->getMessage());
}

if ($token->getRefreshToken() === null) {
    fail('No refresh token. Request the "offline_access" scope and check that your client may use it.');
}

// 4. Store the refresh token, for example with the user or organisation in your database.
echo 'Logged in to Twinfield.', PHP_EOL, PHP_EOL;
echo 'Organisation:  ', $validation->organisation->code, PHP_EOL;
echo 'User:          ', $validation->organisationUserCode, PHP_EOL;
echo 'Cluster:       ', $validation->clusterUrl, PHP_EOL;
echo 'Access token:  valid until ', date('Y-m-d H:i:s', (int) $token->getExpires()), PHP_EOL;
echo 'Refresh token: ', $token->getRefreshToken(), PHP_EOL, PHP_EOL;
echo 'Store the refresh token and use it with RefreshTokenAccessTokenProvider,', PHP_EOL;
echo 'see examples/getting-started/authentication.', PHP_EOL;
