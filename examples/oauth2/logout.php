<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();

$_SESSION = [];
session_destroy();

$cache = getCachePool();
$cache->deleteItem('oauth_token');
$cache->deleteItem('access_token_validation');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Logged Out</title>
    <meta http-equiv="refresh" content="2;url=index.php">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="redirect-page">
    <div class="redirect-container">
        <div class="info-redirect">
            <h2>👋 Logged Out Successfully</h2>
            <p>Your session and stored tokens have been cleared.</p>
            <div class="spinner spinner-info"></div>
            <p class="redirect-note">Redirecting to home page...</p>
        </div>
    </div>
</body>
</html>
