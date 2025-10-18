<?php

declare(strict_types=1);

namespace NksHub\NetteSso\DI;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use NksHub\NetteSso\OAuth2\SsoProvider;

/**
 * Nette DI Extension for SSO authentication
 *
 * Registers SsoProvider service with OAuth2 configuration.
 *
 * Example configuration:
 * <code>
 * extensions:
 *     sso: NksHub\NetteSso\DI\SsoExtension
 *
 * sso:
 *     clientId: 'your-client-id'
 *     clientSecret: 'your-client-secret'
 *     redirectUri: 'https://your-domain.com/auth/callback'
 *     authorizeUrl: 'https://sso.nks-hub.cz/application/o/authorize/'
 *     tokenUrl: 'https://sso.nks-hub.cz/application/o/token/'
 *     userinfoUrl: 'https://sso.nks-hub.cz/application/o/userinfo/'
 *     adminGroups: ['admin', 'superadmin', 'moderator']
 * </code>
 */
class SsoExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'clientId' => Expect::string()->required()
				->dynamic()
				->assert(fn($v) => !empty($v), 'clientId cannot be empty'),

			'clientSecret' => Expect::string()->required()
				->dynamic()
				->assert(fn($v) => !empty($v), 'clientSecret cannot be empty'),

			'redirectUri' => Expect::string()->required()
				->dynamic()
				->assert(fn($v) => filter_var($v, FILTER_VALIDATE_URL) !== false, 'redirectUri must be a valid URL'),

			'authorizeUrl' => Expect::string()->required()
				->dynamic()
				->assert(fn($v) => filter_var($v, FILTER_VALIDATE_URL) !== false, 'authorizeUrl must be a valid URL'),

			'tokenUrl' => Expect::string()->required()
				->dynamic()
				->assert(fn($v) => filter_var($v, FILTER_VALIDATE_URL) !== false, 'tokenUrl must be a valid URL'),

			'userinfoUrl' => Expect::string()->required()
				->dynamic()
				->assert(fn($v) => filter_var($v, FILTER_VALIDATE_URL) !== false, 'userinfoUrl must be a valid URL'),

			'adminGroups' => Expect::listOf('string')->default([
				'admin',
				'superadmin',
				'administrators',
				'moderator',
				'superadmin-webs',
				'authentik admins'
			]),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Register SsoProvider service
		$builder->addDefinition($this->prefix('provider'))
			->setFactory(SsoProvider::class, [
				'clientId' => $config->clientId,
				'clientSecret' => $config->clientSecret,
				'redirectUri' => $config->redirectUri,
				'authorizeUrl' => $config->authorizeUrl,
				'tokenUrl' => $config->tokenUrl,
				'userinfoUrl' => $config->userinfoUrl,
				'adminGroups' => $config->adminGroups,
			])
			->setAutowired(true);
	}
}
