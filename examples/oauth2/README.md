# Twinfield OAuth2 Example

This directory contains a complete, working example of OAuth2 authentication with the Twinfield API using PHP's built-in web server.

## 📋 Prerequisites

- PHP 8.3 or higher
- Composer dependencies installed (run `composer install` in the project root)
- Twinfield OAuth2 credentials (Client ID and Client Secret)

## 🚀 Quick Start

### 1. Configure Environment Variables

Copy the example environment file and configure your credentials:

```bash
cd examples/oauth2
cp .env.example .env
```

Edit `.env` and add your Twinfield OAuth2 credentials:

```env
TWINFIELD_CLIENT_ID=your_actual_client_id
TWINFIELD_CLIENT_SECRET=your_actual_client_secret
TWINFIELD_REDIRECT_URI=http://localhost:8000/callback.php
TWINFIELD_SCOPES=openid twf.user twf.organisationUser twf.organisation
```

### 2. Start the Development Server

From the `examples/oauth2` directory, run:

```bash
php -S localhost:8000
```

### 3. Open in Browser

Navigate to:

```
http://localhost:8000
```

## 📁 Files Overview

- **index.php** - Main page with OAuth2 login and refresh token import form
- **callback.php** - OAuth2 callback handler (receives the authorization code)
- **refresh.php** - Token refresh endpoint
- **logout.php** - Clears session and cached tokens
- **config.php** - Configuration loader using vlucas/phpdotenv and PSR-6 caching helper functions
- **import_token.php** - CLI script to import a refresh token by command line argument
- **.env.example** - Template for environment variables
- **.env** - Your actual credentials (git-ignored)

## 🔐 OAuth2 Flow

1. User clicks "Login with Twinfield" on index.php
2. User is redirected to Twinfield's authorization page
3. After approval, Twinfield redirects back to callback.php with an authorization code
4. callback.php exchanges the code for an access token
5. Token is automatically saved to PSR-6 cache with expiration
6. User is redirected back to index.php, now authenticated

## 🔄 Token Management

Tokens are now stored using **PSR-6 caching** (via Symfony's FilesystemAdapter), which provides several advantages:

- Automatic expiration based on token TTL
- Flexible backend (can be swapped to Redis, Memcached, etc. without code changes)
- Cleaner cache management without manual file handling
- Secure cache directory (`.cache/`, added to `.gitignore`)

### Automatic Token Storage

- **Access tokens** are automatically saved to PSR-6 cache after OAuth2 callback
- **Refresh tokens** are stored alongside access tokens
- **Access token validation** is cached with a 1-hour TTL
- Tokens automatically expire based on their expiration timestamp

### Manually Importing Existing Tokens

If you already have a refresh token from another source, you can import it directly:

#### Option 1: Web UI (Recommended)
1. On the "Not Authenticated" page, find the "or import refresh token" section
2. Paste your refresh token in the input field
3. Click "➕ Import Refresh Token"
4. The system will automatically fetch a new access token and authenticate you

#### Option 2: CLI Script
```bash
php import_token.php "your_actual_refresh_token_here"
```

The tokens will be imported into the PSR-6 cache and you can start using the application immediately.

## 🛡️ Security Features

- CSRF protection using OAuth2 state parameter
- **PSR-6 cache-based token storage** with automatic expiration
- Secure cache directory (`.cache/`, git-ignored)
- Environment variables for sensitive credentials using [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
- Session management for state validation
- Token expiration checking with automatic invalidation

## 📝 Common Scopes

Configure the scopes you need in your `.env` file:

- `openid` - Basic OpenID Connect
- `twf.user` - Access to user information
- `twf.organisationUser` - Access to organization user data
- `twf.organisation` - Access to organization data

## 🔧 Customization

You can modify the scopes by editing the `TWINFIELD_SCOPES` variable in your `.env` file. Multiple scopes should be space-separated.

## 🐛 Troubleshooting

### "Missing required environment variable"

Make sure you've copied `.env.example` to `.env` and filled in all required values.

### "Invalid redirect URI"

Ensure the `TWINFIELD_REDIRECT_URI` in your `.env` matches exactly what you configured in your Twinfield OAuth2 application settings.

### "Invalid state"

This is a CSRF protection measure. Clear your browser cookies/session and try again.

### Cache security

The `.cache/` directory (where tokens are stored) is added to `.gitignore` to prevent secrets from being committed. The cache adapter provides secure storage for sensitive tokens.

## 📚 Learn More

- [Twinfield API Documentation](https://accounting.twinfield.com/webservices/documentation/)
- [OAuth2 Client Library](https://github.com/thephpleague/oauth2-client)
- [Twinfield OAuth2 Guide](https://accounting.twinfield.com/webservices/documentation/#/ApiReference/Authentication/OpenIdConnect)
