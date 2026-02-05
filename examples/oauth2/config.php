<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Cache\CacheItemPoolInterface;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dotenv->required(['TWINFIELD_CLIENT_ID', 'TWINFIELD_CLIENT_SECRET', 'TWINFIELD_REDIRECT_URI']);

/**
 * Get PSR-6 cache pool instance
 */
function getCachePool(): CacheItemPoolInterface
{
    static $cache = null;
    
    if ($cache === null) {
        $cache = new FilesystemAdapter('twinfield', 0, __DIR__ . '/.cache');
    }
    
    return $cache;
}

/**
 * Save token to cache
 */
function saveToken(\League\OAuth2\Client\Token\AccessToken $accessToken): void
{
    $cache = getCachePool();
    $item = $cache->getItem('oauth_token');
    
    if ($accessToken->getExpires()) {
        $item->expiresAt(\DateTimeImmutable::createFromFormat('U', (string)$accessToken->getExpires()));
    }
    
    $item->set($accessToken);
    $cache->save($item);
}

/**
 * Save access token validation data to cache
 */
function saveAccessTokenValidation(\SolveBeam\Twinfield\OAuth2\AccessTokenValidation $validation): void
{
    $cache = getCachePool();
    $item = $cache->getItem('access_token_validation');

    if ($validation->exp > 0) {
        $item->expiresAt(\DateTimeImmutable::createFromFormat('U', (string) $validation->exp));
    }
    
    $item->set($validation);
    $cache->save($item);
}

/**
 * Load token from cache
 */
function loadToken(): ?\League\OAuth2\Client\Token\AccessToken
{
    $cache = getCachePool();
    $item = $cache->getItem('oauth_token');
    
    if (!$item->isHit()) {
        return null;
    }
    
    $data = $item->get();
    return $data instanceof \League\OAuth2\Client\Token\AccessToken ? $data : null;
}

/**
 * Load access token validation data from cache
 */
function loadAccessTokenValidation(): ?\SolveBeam\Twinfield\OAuth2\AccessTokenValidation
{
    $cache = getCachePool();
    $item = $cache->getItem('access_token_validation');
    
    if (!$item->isHit()) {
        return null;
    }
    
    $data = $item->get();
    return $data instanceof \SolveBeam\Twinfield\OAuth2\AccessTokenValidation ? $data : null;
}

/**
 * Import refresh token and fetch new access token from Twinfield
 * Returns the new token data or throws exception on failure
 */
function importRefreshTokenAndGetAccessToken(string $refreshToken): array
{
    $provider = new \SolveBeam\Twinfield\OAuth2\Provider\Twinfield([
        'clientId'     => $_ENV['TWINFIELD_CLIENT_ID'],
        'clientSecret' => $_ENV['TWINFIELD_CLIENT_SECRET'],
        'redirectUri'  => $_ENV['TWINFIELD_REDIRECT_URI'],
    ]);
    
    try {
        $accessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $refreshToken
        ]);
        
        saveToken($accessToken);
        $validationData = $provider->getAccessTokenValidation($accessToken);
        saveAccessTokenValidation($validationData);
        
        return $accessToken->jsonSerialize();
    } catch (\Exception $e) {
        throw new RuntimeException('Failed to refresh token: ' . $e->getMessage());
    }
}

/**
 * Check if token is expired
 */
function isTokenExpired(?\League\OAuth2\Client\Token\AccessToken $accessToken): bool
{
    if ($accessToken === null || !$accessToken->getExpires()) {
        return true;
    }
    
    return time() >= $accessToken->getExpires();
}
