<?php

declare(strict_types=1);

namespace NksHub\NetteSso\OAuth2;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Security\SimpleIdentity;
use NksHub\NetteSso\Exceptions\AuthenticationFailedException;

/**
 * OAuth2/OpenID Connect Provider for SSO authentication
 *
 * Provides secure OAuth2 authentication flow with CSRF protection,
 * role mapping, and Nette Identity creation.
 */
class SsoProvider
{
	private GenericProvider $provider;
	private SessionSection $session;

	/**
	 * @param string[] $adminGroups List of groups that grant admin access
	 */
	public function __construct(
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly string $redirectUri,
		private readonly string $authorizeUrl,
		private readonly string $tokenUrl,
		private readonly string $userinfoUrl,
		private readonly array $adminGroups,
		Session $sessionManager,
	) {
		$this->initializeProvider();
		$this->session = $sessionManager->getSection('sso');
	}

	private function initializeProvider(): void
	{
		$this->provider = new GenericProvider([
			'clientId' => $this->clientId,
			'clientSecret' => $this->clientSecret,
			'redirectUri' => $this->redirectUri,
			'urlAuthorize' => $this->authorizeUrl,
			'urlAccessToken' => $this->tokenUrl,
			'urlResourceOwnerDetails' => $this->userinfoUrl,
			'scopes' => 'openid email profile groups',
		]);
	}

	/**
	 * Get authorization URL for redirect to SSO login
	 *
	 * Generates authorization URL and stores state token in session for CSRF protection.
	 *
	 * @param array<string, mixed> $options Additional OAuth2 options
	 */
	public function getAuthorizationUrl(array $options = []): string
	{
		$url = $this->provider->getAuthorizationUrl(array_merge([
			'scope' => ['openid', 'email', 'profile', 'groups']
		], $options));

		// Store state token in session for CSRF validation
		$this->session->state = $this->provider->getState();

		return $url;
	}

	/**
	 * Get state token for CSRF protection
	 *
	 * Returns the state token stored in session after getAuthorizationUrl() call.
	 */
	public function getState(): string
	{
		return $this->session->state ?? '';
	}

	/**
	 * Validate state token from OAuth2 callback
	 *
	 * @throws AuthenticationFailedException When state validation fails
	 */
	public function validateState(string $state): void
	{
		$sessionState = $this->session->state ?? null;

		if (!$sessionState || $sessionState !== $state) {
			throw new AuthenticationFailedException('Invalid state token (CSRF protection failed)');
		}

		// Clear state after validation
		unset($this->session->state);
	}

	/**
	 * Exchange authorization code for access token
	 *
	 * @throws AuthenticationFailedException
	 */
	public function getAccessToken(string $code): AccessToken
	{
		try {
			return $this->provider->getAccessToken('authorization_code', [
				'code' => $code
			]);
		} catch (IdentityProviderException $e) {
			throw new AuthenticationFailedException(
				'Failed to exchange authorization code for access token: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Get user info from access token
	 *
	 * @return array{
	 *   sub: string,
	 *   email: string,
	 *   name: string,
	 *   preferred_username?: string,
	 *   groups?: string[],
	 *   picture?: string
	 * }
	 * @throws AuthenticationFailedException
	 */
	public function getUserInfo(AccessToken $token): array
	{
		try {
			$user = $this->provider->getResourceOwner($token);
			return $user->toArray();
		} catch (IdentityProviderException $e) {
			throw new AuthenticationFailedException(
				'Failed to retrieve user info: ' . $e->getMessage(),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Complete OAuth2 authentication flow
	 *
	 * Validates state token, exchanges code for token, and retrieves user data.
	 *
	 * @return array{
	 *   user: array,
	 *   token: AccessToken
	 * }
	 * @throws AuthenticationFailedException
	 */
	public function authenticate(string $code, string $state): array
	{
		// Validate CSRF token
		$this->validateState($state);

		// Exchange code for token
		$token = $this->getAccessToken($code);

		// Get user info
		$user = $this->getUserInfo($token);

		return [
			'user' => $user,
			'token' => $token
		];
	}

	/**
	 * Check if user has admin access based on groups
	 *
	 * @param array<string, mixed> $userData User data from OAuth2 provider
	 */
	public function hasAdminAccess(array $userData): bool
	{
		$groups = $userData['groups'] ?? [];

		foreach ($groups as $group) {
			if (in_array(strtolower($group), array_map('strtolower', $this->adminGroups), true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create Nette Identity from OAuth2 user data
	 *
	 * Maps OAuth2 user data and groups to Nette Identity with role.
	 *
	 * @param array<string, mixed> $userData User data from OAuth2 provider
	 * @param string|null $roleOverride Override role detection (e.g., from database)
	 */
	public function createIdentity(array $userData, ?string $roleOverride = null): SimpleIdentity
	{
		$groups = $userData['groups'] ?? [];
		$role = $roleOverride ?? 'viewer';

		// Auto-detect role from groups if not overridden
		if (!$roleOverride) {
			$role = $this->detectRoleFromGroups($groups);
		}

		return new SimpleIdentity(
			$userData['sub'], // OAuth2 subject (unique ID)
			$role,
			[
				'email' => $userData['email'] ?? null,
				'name' => $userData['name'] ?? $userData['preferred_username'] ?? 'Unknown',
				'username' => $userData['preferred_username'] ?? null,
				'picture' => $userData['picture'] ?? null,
				'groups' => $groups,
				'sso_provider' => 'authentik'
			]
		);
	}

	/**
	 * Detect user role from OAuth2 groups
	 *
	 * @param string[] $groups
	 */
	private function detectRoleFromGroups(array $groups): string
	{
		$groupsLower = array_map('strtolower', $groups);

		// Priority: superadmin > admin > moderator > viewer
		if (in_array('superadmin', $groupsLower, true) || in_array('superadmin-webs', $groupsLower, true)) {
			return 'superadmin';
		}

		if (in_array('admin', $groupsLower, true) || in_array('administrators', $groupsLower, true) || in_array('authentik admins', $groupsLower, true)) {
			return 'admin';
		}

		if (in_array('moderator', $groupsLower, true)) {
			return 'moderator';
		}

		return 'viewer';
	}

	/**
	 * Get configured admin groups
	 *
	 * @return string[]
	 */
	public function getAdminGroups(): array
	{
		return $this->adminGroups;
	}
}
