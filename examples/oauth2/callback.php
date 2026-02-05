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

if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    $errorDescription = htmlspecialchars($_GET['error_description'] ?? 'Unknown error');
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>OAuth2 Error</title>
        <link rel=\"stylesheet\" href=\"styles.css\">
    </head>
    <body class=\"redirect-page\">
        <div class=\"redirect-container\">
            <div class='error-redirect'>
                <h2>❌ OAuth2 Error</h2>
                <p><strong>Error:</strong> {$error}</p>
                <p><strong>Description:</strong> {$errorDescription}</p>
            </div>
            <a href='index.php' class='btn'>← Back to Home</a>
        </div>
    </body>
    </html>";
    exit;
}

// Validate state to prevent CSRF
if (!isset($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Invalid State</title>
        <link rel=\"stylesheet\" href=\"styles.css\">
    </head>
    <body class=\"redirect-page\">
        <div class=\"redirect-container\">
            <div class='status-card warning'>
                <h2>⚠️ Invalid State</h2>
                <p>The OAuth2 state parameter is invalid or missing. This could be a CSRF attack.</p>
                <p>Please start the authentication process again from the beginning.</p>
            </div>
            <a href='index.php' class='btn'>← Back to Home</a>
        </div>
    </body>
    </html>";
    exit;
}

try {
    $accessToken = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);
    
    saveToken($accessToken);
    $validationData = $provider->getAccessTokenValidation($accessToken);
    saveAccessTokenValidation($validationData);
    unset($_SESSION['oauth2state']);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Authentication Successful</title>
        <meta http-equiv='refresh' content='2;url=index.php'>
        <link rel=\"stylesheet\" href=\"styles.css\">
    </head>
    <body class=\"redirect-page\">
        <div class=\"redirect-container\">
            <div class='success'>
                <h2>✅ Authentication Successful!</h2>
                <p>Your OAuth2 token has been saved successfully.</p>
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
        <title>Authentication Error</title>
        <link rel=\"stylesheet\" href=\"styles.css\">
    </head>
    <body class=\"redirect-page\">
        <div class=\"redirect-container\">
            <div class='error-redirect'>
                <h2>❌ Authentication Error</h2>
                <p>{$errorMessage}</p>
                <pre>" . htmlspecialchars(print_r($e->getResponseBody(), true)) . "</pre>
            </div>
            <a href='index.php' class='btn'>← Try Again</a>
        </div>
    </body>
    </html>";
}
