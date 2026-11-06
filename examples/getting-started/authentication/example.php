<?php

declare(strict_types=1);

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield as TwinfieldProvider;
use SolveBeam\Twinfield\OAuth2\RefreshTokenAccessTokenProvider;
use SolveBeam\Twinfield\Twinfield;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

require __DIR__ . '/../../../vendor/autoload.php';

function requireEnv(string $name): string
{
    return getenv($name) ?: throw new RuntimeException("Set the environment variable {$name}.");
}

// 1. The OAuth provider with your client from the Twinfield developer portal.
$provider = new TwinfieldProvider([
    'clientId' => requireEnv('TWINFIELD_CLIENT_ID'),
    'clientSecret' => requireEnv('TWINFIELD_CLIENT_SECRET'),
    'redirectUri' => requireEnv('TWINFIELD_REDIRECT_URI'),
]);

// 2. Access tokens from the refresh token you stored after the user logged in.
//    The PSR-6 cache shares access tokens between requests.
$tokens = new RefreshTokenAccessTokenProvider(
    $provider,
    refreshToken: requireEnv('TWINFIELD_REFRESH_TOKEN'),
    cache: new FilesystemAdapter('twinfield'),
);

// 3. The client, for one company (office).
$twinfield = Twinfield::create($tokens)->company(requireEnv('TWINFIELD_COMPANY'));

echo 'Connected to ', $tokens->clusterUrl(), PHP_EOL;
echo 'Company ', $twinfield->companyCode(), ' has access to ', count($twinfield->offices()->list()), ' offices.', PHP_EOL;
