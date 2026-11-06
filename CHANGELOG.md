# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-11-06

### Added
- `roleGroups` option (and `SsoProvider::DEFAULT_ROLE_GROUPS`): the groups that give each Nette role are configurable. The defaults match the previous hard-coded names.
- Tests (Nette Tester) for the scope separator, PKCE verifier storage, nonce check, state check and role mapping.

### Fixed
- The nonce is verified against the `nonce` claim of the ID token returned by the token endpoint, not against the userinfo response, which does not contain it.
- The `SsoExtension` example uses `sso.example.com` instead of a real host name.

### Known limitations
- The signature of the ID token is not verified. The token is read directly from the token endpoint over TLS and only the `nonce` claim is checked.
- `sso_provider` in the identity data is always `authentik`.

## [1.1.0] - 2026-03-22

### Added
- PKCE (S256): the code verifier is kept in the session between the redirect and the callback.
- Nonce generation and validation.

### Fixed
- State token is compared in constant time.
- Scope is separated by a space in the authorization URL, as OpenID Connect requires.

## [1.0.0] - 2025-10-18

### Added
- Initial release of NKS Hub Nette SSO extension
- OAuth2/OpenID Connect authentication support
- CSRF protection with state token validation
- Automatic role mapping from OAuth2 groups to Nette roles
- Admin access control based on configurable groups
- Nette Security integration with SimpleIdentity
- Session-based state management
- Comprehensive error handling with custom exceptions
- PHP 8.1+ support (8.1, 8.2, 8.3, 8.4)
- Type-safe implementation with strict types
- Extensive documentation and examples
- MIT license

### Features
- `SsoExtension` - Nette DI Extension for easy configuration
- `SsoProvider` - Main OAuth2 provider with authentication flow
- `AuthenticationFailedException` - Exception for authentication errors
- `SsoException` - Base exception class
- Configurable admin groups
- Automatic role detection from OAuth2 groups
- Custom role override support
- Identity creation with user data mapping

### Security
- CSRF protection via OAuth2 state tokens
- Session-based state validation
- Secure configuration validation
- Type-safe implementation
- No sensitive data exposure in error messages

### Documentation
- Complete README.md with usage examples
- OAuth2 flow diagram
- Security considerations guide
- Troubleshooting section
- Configuration reference
- API documentation

[1.2.0]: https://github.com/nks-hub/nette-sso/releases/tag/v1.2.0
[1.1.0]: https://github.com/nks-hub/nette-sso/releases/tag/v1.1.0
[1.0.0]: https://github.com/nks-hub/nette-sso/releases/tag/v1.0.0
