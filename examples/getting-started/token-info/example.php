<?php

declare(strict_types=1);

use League\OAuth2\Client\Token\AccessToken;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$environment = Example::environment();
$provider = $environment->session->provider();

// Any access token works here, also one from RefreshTokenAccessTokenProvider.
$token = new AccessToken(['access_token' => $environment->session->accessToken()]);

// Access token validation: organisation, user and the cluster to send API calls to.
$validation = $provider->getAccessTokenValidation($token);

echo 'Access token validation', PHP_EOL;
echo '  Organisation code:  ', $validation->organisationCode, PHP_EOL;
echo '  Organisation ID:    ', $validation->organisationId, PHP_EOL;
echo '  User code:          ', $validation->organisationUserCode, PHP_EOL;
echo '  Cluster URL:        ', $validation->clusterUrl, PHP_EOL;
echo '  Expires:            ', date('Y-m-d H:i:s', $validation->exp), PHP_EOL, PHP_EOL;

// User info: the claims of the scopes you requested at login.
$user = $provider->getResourceOwner($token);

echo 'User info', PHP_EOL;

foreach ($user->toArray() as $claim => $value) {
    echo '  ', str_pad($claim . ':', 26), is_scalar($value) ? $value : json_encode($value), PHP_EOL;
}
