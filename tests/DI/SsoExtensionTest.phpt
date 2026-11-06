<?php

declare(strict_types=1);

namespace NksHub\NetteSso\Tests\DI;

use Nette\Bootstrap\Configurator;
use NksHub\NetteSso\DI\SsoExtension;
use NksHub\NetteSso\OAuth2\SsoProvider;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$temp = tempDir();
$configurator = new Configurator();
$configurator->setTempDirectory($temp);
$configurator->setDebugMode(false);
$configurator->addConfig([
	'extensions' => ['sso' => SsoExtension::class],
	'session' => ['savePath' => $temp],
	'sso' => [
		'clientId' => 'client',
		'clientSecret' => 'secret',
		'redirectUri' => 'https://app.example.com/callback',
		'authorizeUrl' => 'https://sso.example.com/authorize/',
		'tokenUrl' => 'https://sso.example.com/token/',
		'userinfoUrl' => 'https://sso.example.com/userinfo/',
		'roleGroups' => ['owner' => ['sso-owners']],
	],
]);

$provider = $configurator->createContainer()->getByType(SsoProvider::class);

Assert::same('owner', $provider->createIdentity(['sub' => 'u', 'groups' => ['sso-owners']])->getRoles()[0]);
Assert::same('viewer', $provider->createIdentity(['sub' => 'u', 'groups' => ['admin']])->getRoles()[0]);
