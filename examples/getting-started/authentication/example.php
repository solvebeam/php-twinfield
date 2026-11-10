<?php

declare(strict_types=1);

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield as TwinfieldProvider;
use SolveBeam\Twinfield\OAuth2\RefreshTokenAccessTokenProvider;
use SolveBeam\Twinfield\Twinfield;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

require __DIR__ . '/../../../vendor/autoload.php';

$requireEnv = static fn (string $name): string
    => getenv($name) ?: throw new RuntimeException("Set the environment variable {$name}.");

// 1. The OAuth provider with your client from the Twinfield developer portal.
$provider = new TwinfieldProvider([
    'clientId' => $requireEnv('TWINFIELD_CLIENT_ID'),
    'clientSecret' => $requireEnv('TWINFIELD_CLIENT_SECRET'),
    'redirectUri' => $requireEnv('TWINFIELD_REDIRECT_URI'),
]);

// 2. Access tokens from the refresh token you stored after the user logged in.
//    The PSR-6 cache shares access tokens between requests.
$tokens = new RefreshTokenAccessTokenProvider(
    $provider,
    refreshToken: $requireEnv('TWINFIELD_REFRESH_TOKEN'),
    cache: new FilesystemAdapter('twinfield'),
);

// 3. The client, for one company (office).
$twinfield = Twinfield::create($tokens)->company($requireEnv('TWINFIELD_COMPANY'));

try {
    echo 'Connected to ', $tokens->clusterUrl(), PHP_EOL;
    echo 'Company ', $twinfield->companyCode(), ' has access to ', count($twinfield->offices()->list()), ' offices.',
        PHP_EOL;
} catch (IdentityProviderException $exception) {
    // The refresh token no longer works (revoked, user locked, organisation inactive, …).
    // Ask the user to log in again, see examples/getting-started/login.
    $error = $exception->getResponseBody()['error'] ?? 'unknown';

    echo "Log in to Twinfield again ({$error}: {$exception->getMessage()}).", PHP_EOL;
    exit(1);
}

// 4. Store the refresh token again if Twinfield returned a new one.
if ($tokens->refreshToken() !== $requireEnv('TWINFIELD_REFRESH_TOKEN')) {
    echo 'Store the new refresh token: ', $tokens->refreshToken(), PHP_EOL;
}
