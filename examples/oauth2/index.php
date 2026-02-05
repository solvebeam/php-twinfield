<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

session_start();

// Handle refresh token import
$refreshTokenImportError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_refresh_token'])) {
    $refreshToken = trim($_POST['refresh_token'] ?? '');
    
    if (empty($refreshToken)) {
        $refreshTokenImportError = 'Please enter a refresh token';
    } else {
        try {
            importRefreshTokenAndGetAccessToken($refreshToken);
            // Redirect to refresh the page and show new auth status
            header('Location: index.php');
            exit;
        } catch (RuntimeException $e) {
            $refreshTokenImportError = 'Failed to import refresh token: ' . $e->getMessage();
        }
    }
}

$provider = new Twinfield([
    'clientId'     => $_ENV['TWINFIELD_CLIENT_ID'],
    'clientSecret' => $_ENV['TWINFIELD_CLIENT_SECRET'],
    'redirectUri'  => $_ENV['TWINFIELD_REDIRECT_URI'],
]);

$accessToken = loadToken();
$validationData = loadAccessTokenValidation();

$validationContext = null;

if ($accessToken !== null && !isTokenExpired($accessToken)) {
    if ($validationData !== null) {
        $validationContext = $validationData->toArray();
    }
}

$resourceOwner = null;
$error = null;

if ($accessToken !== null) {
    try {
        $resourceOwner = $provider->getResourceOwner($accessToken);
    } catch (IdentityProviderException $e) {
        $error = 'Failed to get resource owner: ' . $e->getMessage();
        // Token might be invalid, clear it
        $accessToken = null;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twinfield OAuth2 Example</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>🔐 Twinfield OAuth2 Example</h1>
        <p class="subtitle">Test OAuth2 authentication with Twinfield API</p>
        
        <?php if ($error !== null): ?>
            <div class="status-card error">
                <div class="status-title">❌ Error</div>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($accessToken !== null && $resourceOwner !== null): ?>
            <div class="status-card">
                <div class="status-title">✅ Authenticated Successfully</div>
                <p>You are currently authenticated with Twinfield.</p>
            </div>
            
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">User ID:</span>
                    <span class="info-value"><?= htmlspecialchars($resourceOwner->getId() ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Token Type:</span>
                    <span class="info-value"><?= htmlspecialchars($accessToken->getToken() !== null ? 'Bearer' : 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Expires:</span>
                    <span class="info-value">
                        <?php
                        $expires = $accessToken->getExpires();
                        if ($expires !== null) {
                            $expiresDate = date('Y-m-d H:i:s', $expires);
                            $remainingMinutes = max(0, (int) (($expires - time()) / 60));
                            echo htmlspecialchars($expiresDate) . " (in {$remainingMinutes} minutes)";
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Has Refresh Token:</span>
                    <span class="info-value">
                        <?php if ($accessToken->getRefreshToken() !== null): ?>
                            <span class="badge badge-success">Yes</span>
                        <?php else: ?>
                            <span class="badge badge-warning">No</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="code-block">
                <strong>Resource Owner Data:</strong>
                <pre><?= htmlspecialchars(json_encode($resourceOwner->toArray(), JSON_PRETTY_PRINT)) ?></pre>
            </div>

            <?php if ($validationContext !== null): ?>
                <div class="code-block">
                    <strong>Access Token Validation:</strong>
                    <pre><?= htmlspecialchars(json_encode($validationContext, JSON_PRETTY_PRINT)) ?></pre>
                </div>
            <?php endif; ?>
            
            <div class="code-block">
                <strong>Access Token (first 50 chars):</strong>
                <pre><?= htmlspecialchars(substr($accessToken->getToken(), 0, 50)) ?>…</pre>
            </div>
            
            <div class="btn-group">
                <a href="refresh.php" class="btn">🔄 Refresh Token</a>
                <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
            </div>
            
        <?php else: ?>
            <div class="status-card warning">
                <div class="status-title">⚠️ Not Authenticated</div>
                <p>You need to authenticate with Twinfield to continue.</p>
            </div>
            
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Client ID:</span>
                    <span class="info-value"><?= htmlspecialchars($_ENV['TWINFIELD_CLIENT_ID']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Redirect URI:</span>
                    <span class="info-value"><?= htmlspecialchars($_ENV['TWINFIELD_REDIRECT_URI']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Scopes:</span>
                    <span class="info-value"><?= htmlspecialchars($_ENV['TWINFIELD_SCOPES'] ?? 'openid') ?></span>
                </div>
            </div>
            
            <div class="btn-group">
                <?php
                // Generate authorization URL
                $scopes = explode(' ', $_ENV['TWINFIELD_SCOPES'] ?? 'openid');
                $authorizationUrl = $provider->getAuthorizationUrl(['scope' => $scopes]);
                
                $_SESSION['oauth2state'] = $provider->getState();
                ?>
                <a href="<?= htmlspecialchars($authorizationUrl) ?>" class="btn">🔑 Login with Twinfield</a>
            </div>
            
            <div class="divider">or import refresh token</div>
            
            <?php if ($refreshTokenImportError !== null): ?>
                <div class="status-card error">
                    <div class="status-title">❌ Import Error</div>
                    <p><?= htmlspecialchars($refreshTokenImportError) ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="import-form">
                <div class="form-group">
                    <label for="refresh_token">Refresh Token:</label>
                    <input 
                        type="password" 
                        id="refresh_token" 
                        name="refresh_token" 
                        placeholder="Paste your refresh token here" 
                        required
                        autocomplete="off"
                    >
                </div>
                <button type="submit" name="import_refresh_token" class="form-submit">📥 Import Refresh Token</button>
                <div class="form-bottom-note">
                    This will fetch a new access token and authenticate you automatically.
                </div>
            </form>
        <?php endif; ?>
        
        <div class="form-bottom-section">
            <p><strong>Setup Instructions:</strong></p>
            <ol class="instructions-list">
                <li>Copy <code>.env.example</code> to <code>.env</code></li>
                <li>Configure your Twinfield OAuth2 credentials in <code>.env</code></li>
                <li>Run: <code>php -S localhost:8000</code> (from the oauth2 directory)</li>
                <li>Visit: <code>http://localhost:8000</code></li>
            </ol>
        </div>
    </div>
</body>
</html>
