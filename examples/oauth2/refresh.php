<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

session_start();

$provider = new Twinfield([
    'clientId'     => $_ENV['TWINFIELD_CLIENT_ID'],
    'clientSecret' => $_ENV['TWINFIELD_CLIENT_SECRET'],
    'redirectUri'  => $_ENV['TWINFIELD_REDIRECT_URI'],
]);

$oldAccessToken = loadToken();

if ($oldAccessToken === null || !$oldAccessToken->getRefreshToken()) {
    header('Location: index.php');
    exit;
}

try {
    $newAccessToken = $provider->getAccessToken('refresh_token', [
        'refresh_token' => $oldAccessToken->getRefreshToken()
    ]);
    
    saveToken($newAccessToken);
    $validationData = $provider->getAccessTokenValidation($newAccessToken);
    saveAccessTokenValidation($validationData);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Token Refreshed</title>
        <meta http-equiv='refresh' content='2;url=index.php'>
        <link rel=\"stylesheet\" href=\"styles.css\">
    </head>
    <body class=\"redirect-page\">
        <div class=\"redirect-container\">
            <div class='success'>
                <h2>🔄 Token Refreshed Successfully!</h2>
                <p>Your access token has been refreshed and saved.</p>
                <div class='spinner spinner-success'></div>
                <p class='redirect-note'>Redirecting to home page...</p>
            </div>
        </div>
    </body>
    </html>";
    
} catch (IdentityProviderException $e) {
    $errorMessage = htmlspecialchars($e->getMessage());
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Refresh Error</title>
        <link rel=\"stylesheet\" href=\"styles.css\">
    </head>
    <body class=\"redirect-page\">
        <div class=\"redirect-container\">
            <div class='error-redirect'>
                <h2>❌ Token Refresh Failed</h2>
                <p>{$errorMessage}</p>
                <p>You may need to authenticate again.</p>
            </div>
            <a href='logout.php' class='btn'>Logout and Re-authenticate</a>
        </div>
    </body>
    </html>";
}
