# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-18

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

[1.0.0]: https://github.com/nks-hub/nette-sso/releases/tag/v1.0.0
