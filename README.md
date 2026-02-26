[![Latest Stable Version](https://poser.pugx.org/nks-hub/nette-sso/v)](https://packagist.org/packages/nks-hub/nette-sso)
[![Total Downloads](https://poser.pugx.org/nks-hub/nette-sso/downloads)](https://packagist.org/packages/nks-hub/nette-sso)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

# NKS Hub - Nette SSO Extension

Nette DI extension for Single Sign-On authentication via OAuth2/OpenID Connect (Authentik).

## Features

- ✅ **OAuth2/OpenID Connect** authentication flow
- ✅ **CSRF protection** with state token validation
- ✅ **Role mapping** from OAuth2 groups to Nette roles
- ✅ **Admin access control** based on configurable groups
- ✅ **Nette Security integration** with SimpleIdentity
- ✅ **Session-based state management** for secure authentication
- ✅ **PHP 8.1+ support** (8.1, 8.2, 8.3, 8.4)
- ✅ **Type-safe** with strict types and comprehensive PHPDoc

## Requirements

- PHP 8.1 or higher
- Nette Framework 3.0+
- league/oauth2-client 2.7+

## Installation

Install via Composer:

```bash
composer require nks-hub/nette-sso
```

## Configuration

Register the extension in your Nette configuration file:

```neon
extensions:
    sso: NksHub\NetteSso\DI\SsoExtension

sso:
    clientId: 'your-client-id'
    clientSecret: 'your-client-secret'
    redirectUri: 'https://your-domain.com/auth/callback'
    authorizeUrl: 'https://sso.nks-hub.cz/application/o/authorize/'
    tokenUrl: 'https://sso.nks-hub.cz/application/o/token/'
    userinfoUrl: 'https://sso.nks-hub.cz/application/o/userinfo/'
    adminGroups: ['admin', 'superadmin', 'moderator']  # optional
```

### Configuration Parameters

| Parameter | Required | Type | Description |
|-----------|----------|------|-------------|
| `clientId` | ✅ Yes | string | OAuth2 client ID from your SSO provider |
| `clientSecret` | ✅ Yes | string | OAuth2 client secret |
| `redirectUri` | ✅ Yes | string | Callback URL after authentication (must be registered in SSO) |
| `authorizeUrl` | ✅ Yes | string | OAuth2 authorization endpoint URL |
| `tokenUrl` | ✅ Yes | string | OAuth2 token endpoint URL |
| `userinfoUrl` | ✅ Yes | string | OAuth2 user info endpoint URL |
| `adminGroups` | ❌ No | string[] | List of groups that grant admin access (default: see below) |

**Default admin groups:**
- `admin`
- `superadmin`
- `administrators`
- `moderator`
- `superadmin-webs`
- `authentik admins`

### Environment Variables

It's recommended to use environment variables for sensitive data:

```neon
sso:
    clientId: %env.SSO_CLIENT_ID%
    clientSecret: %env.SSO_CLIENT_SECRET%
    redirectUri: %env.SSO_REDIRECT_URI%
    authorizeUrl: %env.SSO_AUTHORIZE_URL%
    tokenUrl: %env.SSO_TOKEN_URL%
    userinfoUrl: %env.SSO_USERINFO_URL%
```

## Usage

### Basic Authentication Flow

Create an authentication presenter:

```php
<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;
use NksHub\NetteSso\OAuth2\SsoProvider;
use NksHub\NetteSso\Exceptions\AuthenticationFailedException;

final class AuthPresenter extends Presenter
{
    public function __construct(
        private readonly SsoProvider $ssoProvider,
    ) {
        parent::__construct();
    }

    /**
     * Step 1: Redirect to SSO login page
     */
    public function actionLogin(): void
    {
        $authUrl = $this->ssoProvider->getAuthorizationUrl();
        $this->redirectUrl($authUrl);
    }

    /**
     * Step 2: Handle OAuth2 callback
     */
    public function actionCallback(): void
    {
        $code = $this->getParameter('code');
        $state = $this->getParameter('state');

        if (!$code || !$state) {
            $this->flashMessage('Chybný požadavek na přihlášení', 'error');
            $this->redirect('Homepage:');
        }

        try {
            // Authenticate user via SSO
            $result = $this->ssoProvider->authenticate($code, $state);
            $userData = $result['user'];

            // Check admin access
            if (!$this->ssoProvider->hasAdminAccess($userData)) {
                $this->flashMessage('Nemáte oprávnění k přístupu do administrace', 'error');
                $this->redirect('Homepage:');
            }

            // Create Nette Identity
            $identity = $this->ssoProvider->createIdentity($userData);

            // Log user in
            $this->getUser()->login($identity);

            $this->flashMessage('Úspěšně přihlášen jako ' . $identity->getData()['name'], 'success');
            $this->redirect('Admin:Dashboard:');

        } catch (AuthenticationFailedException $e) {
            $this->flashMessage('Přihlášení selhalo: ' . $e->getMessage(), 'error');
            $this->redirect('Homepage:');
        }
    }

    /**
     * Logout
     */
    public function actionLogout(): void
    {
        $this->getUser()->logout(true);
        $this->flashMessage('Odhlášen', 'info');
        $this->redirect('Homepage:');
    }
}
```

### Custom Role Mapping

If you want to override role detection (e.g., from database):

```php
// Get user from database
$dbUser = $this->userRepository->findBySsoId($userData['sub']);

// Create identity with custom role
$identity = $this->ssoProvider->createIdentity(
    $userData,
    $dbUser?->role ?? 'viewer'  // Override role from database
);
```

### Checking Admin Access

```php
$userData = $result['user'];

if ($this->ssoProvider->hasAdminAccess($userData)) {
    // User has admin access
}
```

### Getting Configured Admin Groups

```php
$adminGroups = $this->ssoProvider->getAdminGroups();
// Returns: ['admin', 'superadmin', 'moderator', ...]
```

## OAuth2 Flow Diagram

```
┌─────────┐                ┌──────────┐                ┌─────────┐
│ Browser │                │ Your App │                │   SSO   │
└────┬────┘                └────┬─────┘                └────┬────┘
     │                          │                           │
     │  1. GET /auth/login      │                           │
     ├─────────────────────────>│                           │
     │                          │                           │
     │  2. Redirect to SSO      │                           │
     │    + state token         │                           │
     │<─────────────────────────┤                           │
     │                          │                           │
     │  3. GET authorize?state=...                          │
     ├──────────────────────────────────────────────────────>│
     │                          │                           │
     │  4. User login form      │                           │
     │<──────────────────────────────────────────────────────┤
     │                          │                           │
     │  5. Submit credentials   │                           │
     ├──────────────────────────────────────────────────────>│
     │                          │                           │
     │  6. Redirect to callback │                           │
     │    + code + state        │                           │
     │<──────────────────────────────────────────────────────┤
     │                          │                           │
     │  7. GET /auth/callback?code=...&state=...            │
     ├─────────────────────────>│                           │
     │                          │                           │
     │                          │  8. Validate state        │
     │                          │     (CSRF protection)     │
     │                          │                           │
     │                          │  9. POST /token           │
     │                          │    (exchange code)        │
     │                          ├──────────────────────────>│
     │                          │                           │
     │                          │  10. Access token         │
     │                          │<──────────────────────────┤
     │                          │                           │
     │                          │  11. GET /userinfo        │
     │                          ├──────────────────────────>│
     │                          │                           │
     │                          │  12. User data + groups   │
     │                          │<──────────────────────────┤
     │                          │                           │
     │                          │  13. Check admin access   │
     │                          │      Create Identity      │
     │                          │      Login user           │
     │                          │                           │
     │  14. Redirect to admin   │                           │
     │<─────────────────────────┤                           │
     │                          │                           │
```

## Security Considerations

### CSRF Protection

The extension automatically handles CSRF protection using OAuth2 state tokens:

1. When generating the authorization URL, a random state token is created
2. The state token is stored in the user's session
3. After OAuth2 callback, the state parameter is validated against the session
4. If validation fails, `AuthenticationFailedException` is thrown

### State Token Validation

```php
try {
    $result = $this->ssoProvider->authenticate($code, $state);
} catch (AuthenticationFailedException $e) {
    // Invalid state token (potential CSRF attack)
    // Handle error appropriately
}
```

### Secure Configuration

**DO:**
- ✅ Store secrets in environment variables
- ✅ Use HTTPS for redirect URIs
- ✅ Validate user groups before granting access
- ✅ Implement proper error handling

**DON'T:**
- ❌ Commit secrets to version control
- ❌ Use HTTP for redirect URIs in production
- ❌ Trust OAuth2 data without validation
- ❌ Expose detailed error messages to users

## Role Mapping

The extension automatically maps OAuth2 groups to Nette roles with the following priority:

1. **Superadmin** - Groups: `superadmin`, `superadmin-webs`
2. **Admin** - Groups: `admin`, `administrators`, `authentik admins`
3. **Moderator** - Groups: `moderator`
4. **Viewer** - Default fallback role

### Custom Role Mapping

Override role detection by passing a custom role:

```php
// From database
$dbRole = $userRepository->getRoleForSsoId($userData['sub']);

$identity = $this->ssoProvider->createIdentity($userData, $dbRole);
```

## Identity Structure

The created `Nette\Security\SimpleIdentity` contains:

- **ID:** OAuth2 `sub` (subject) - unique user identifier
- **Role:** Detected or overridden role
- **Data:**
  - `email` - User email address
  - `name` - Display name
  - `username` - Preferred username
  - `picture` - Profile picture URL
  - `groups` - Array of OAuth2 groups
  - `sso_provider` - Always `'authentik'`

```php
$identity = $this->ssoProvider->createIdentity($userData);

echo $identity->getId();                    // OAuth2 sub
echo $identity->getRoles()[0];              // 'admin'
echo $identity->getData()['email'];         // 'user@example.com'
echo $identity->getData()['name'];          // 'John Doe'
echo $identity->getData()['groups'];        // ['admin', 'users']
```

## Troubleshooting

### Error: "Invalid state token (CSRF protection failed)"

**Cause:** State token mismatch between session and OAuth2 callback.

**Solutions:**
- Check if sessions are working correctly
- Verify that cookies are enabled
- Ensure `redirectUri` matches exactly in SSO provider settings
- Check if user's browser allows third-party cookies

### Error: "Failed to exchange authorization code for access token"

**Cause:** Invalid OAuth2 credentials or configuration.

**Solutions:**
- Verify `clientId` and `clientSecret` are correct
- Check if `redirectUri` is registered in SSO provider
- Ensure `tokenUrl` is accessible from your server
- Check server logs for detailed error messages

### Error: "Failed to retrieve user info"

**Cause:** Invalid access token or userinfo endpoint configuration.

**Solutions:**
- Verify `userinfoUrl` is correct
- Check if access token has required scopes (`openid`, `email`, `profile`, `groups`)
- Ensure userinfo endpoint is accessible

### Users without admin groups can't access admin area

**Expected behavior.** Only users with groups listed in `adminGroups` configuration can access admin area.

**Solutions:**
- Add user to admin group in SSO provider (Authentik)
- Add user's group to `adminGroups` configuration
- Implement custom role mapping from database

## Testing

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan
```

## Contributing

Contributions are welcome! For major changes, please open an issue first.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: description'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 📧 **Email:** dev@nks-hub.cz
- 🐛 **Bug reports:** [GitHub Issues](https://github.com/nks-hub/nette-sso/issues)

## License

MIT License — see [LICENSE](LICENSE) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/nks-hub">NKS Hub</a>
</p>
