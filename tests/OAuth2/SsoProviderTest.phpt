<?php

declare(strict_types=1);

namespace NksHub\NetteSso\Tests\OAuth2;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Nette\Http\Request;
use Nette\Http\Response as HttpResponse;
use Nette\Http\Session;
use Nette\Http\UrlScript;
use NksHub\NetteSso\Exceptions\AuthenticationFailedException;
use NksHub\NetteSso\OAuth2\SsoProvider;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

const AUTHORIZE = 'https://sso.example.com/authorize/';
const TOKEN = 'https://sso.example.com/token/';
const USERINFO = 'https://sso.example.com/userinfo/';

/** @testCase */
final class SsoProviderTest extends TestCase
{
	private Session $session;

	/** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
	private array $history = [];

	protected function setUp(): void
	{
		$this->session = new Session(new Request(new UrlScript('https://app.example.com/')), new HttpResponse());
		$this->session->setOptions(['savePath' => tempDir()]);
		$this->session->start();
		$this->history = [];
	}

	protected function tearDown(): void
	{
		if ($this->session->isStarted()) {
			$this->session->close();
		}
	}

	public function testScopeIsSeparatedBySpace(): void
	{
		$query = $this->query($this->provider()->getAuthorizationUrl());

		Assert::same('openid email profile groups', $query['scope']);
		Assert::same('S256', $query['code_challenge_method']);
	}

	public function testPkceVerifierSurvivesTheCallbackRequest(): void
	{
		$query = $this->query($this->provider()->getAuthorizationUrl());

		// Callback: a fresh provider instance that shares only the session.
		$callback = $this->provider([$this->tokenResponse($query['nonce']), $this->userinfoResponse()]);
		$callback->authenticate('auth-code', $query['state']);

		parse_str((string) $this->history[0]['request']->getBody(), $body);
		Assert::same('auth-code', $body['code']);
		Assert::true(isset($body['code_verifier']));
		Assert::same($query['code_challenge'], rtrim(strtr(base64_encode(hash('sha256', $body['code_verifier'], true)), '+/', '-_'), '='));
	}

	public function testNonceIsVerifiedFromIdToken(): void
	{
		$query = $this->query($this->provider()->getAuthorizationUrl());
		$result = $this->provider([$this->tokenResponse($query['nonce']), $this->userinfoResponse()])
			->authenticate('c', $query['state']);

		Assert::same('user-sub', $result['user']['sub']);
	}

	public function testNonceMismatchInIdTokenIsRejected(): void
	{
		$query = $this->query($this->provider()->getAuthorizationUrl());
		$provider = $this->provider([$this->tokenResponse('other-nonce'), $this->userinfoResponse()]);

		Assert::exception(
			fn() => $provider->authenticate('c', $query['state']),
			AuthenticationFailedException::class,
			'Invalid nonce%a%',
		);
	}

	public function testNonceInUserinfoOnlyIsNotAccepted(): void
	{
		$query = $this->query($this->provider()->getAuthorizationUrl());
		$provider = $this->provider([
			$this->tokenResponse(null),
			new Response(200, [], (string) json_encode(['sub' => 'user-sub', 'nonce' => $query['nonce']])),
		]);

		Assert::exception(
			fn() => $provider->authenticate('c', $query['state']),
			AuthenticationFailedException::class,
			'Invalid nonce%a%',
		);
	}

	public function testForeignStateIsRejectedBeforeAnyRequest(): void
	{
		$this->provider()->getAuthorizationUrl();
		$provider = $this->provider([$this->tokenResponse('x')]);

		Assert::exception(
			fn() => $provider->authenticate('c', 'forged'),
			AuthenticationFailedException::class,
			'Invalid state token%a%',
		);
		Assert::count(0, $this->history);
	}

	public function testRoleComesFromConfiguredGroups(): void
	{
		$provider = $this->provider(null, ['owner' => ['sso-owners'], 'editor' => ['Sso-Editors']]);

		Assert::same('owner', $provider->createIdentity(['sub' => 'u', 'groups' => ['sso-owners', 'sso-editors']])->getRoles()[0]);
		Assert::same('editor', $provider->createIdentity(['sub' => 'u', 'groups' => ['sso-editors']])->getRoles()[0]);
		Assert::same('viewer', $provider->createIdentity(['sub' => 'u', 'groups' => ['admin']])->getRoles()[0]);
	}

	public function testDefaultRoleGroupsKeepPreviousBehaviour(): void
	{
		$provider = $this->provider();

		Assert::same('superadmin', $provider->createIdentity(['sub' => 'u', 'groups' => ['Superadmin-Webs', 'admin']])->getRoles()[0]);
		Assert::same('admin', $provider->createIdentity(['sub' => 'u', 'groups' => ['Authentik Admins']])->getRoles()[0]);
		Assert::same('moderator', $provider->createIdentity(['sub' => 'u', 'groups' => ['moderator']])->getRoles()[0]);
		Assert::same('viewer', $provider->createIdentity(['sub' => 'u', 'groups' => []])->getRoles()[0]);
	}

	/**
	 * @param list<Response>|null $responses queued answers of the mocked HTTP layer; null keeps the real provider
	 * @param array<string, string[]>|null $roleGroups
	 */
	private function provider(?array $responses = null, ?array $roleGroups = null): SsoProvider
	{
		$provider = new SsoProvider(
			'client', 'secret', 'https://app.example.com/callback', AUTHORIZE, TOKEN, USERINFO,
			['admin'], $this->session, $roleGroups ?? SsoProvider::DEFAULT_ROLE_GROUPS,
		);

		if ($responses === null) {
			return $provider;
		}

		$stack = HandlerStack::create(new MockHandler($responses));
		$stack->push(Middleware::history($this->history));
		$property = new \ReflectionProperty($provider, 'provider');
		$property->setAccessible(true);
		$property->setValue($provider, new GenericProvider(
			[
				'clientId' => 'client',
				'clientSecret' => 'secret',
				'redirectUri' => 'https://app.example.com/callback',
				'urlAuthorize' => AUTHORIZE,
				'urlAccessToken' => TOKEN,
				'urlResourceOwnerDetails' => USERINFO,
				'scopes' => 'openid email profile groups',
				'scopeSeparator' => ' ',
				'pkceMethod' => 'S256',
			],
			['httpClient' => new Client(['handler' => $stack])],
		));

		return $provider;
	}

	private function tokenResponse(?string $nonce): Response
	{
		$body = ['access_token' => 'at', 'token_type' => 'Bearer', 'expires_in' => 3600];
		if ($nonce !== null) {
			$b64 = fn(array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
			$body['id_token'] = $b64(['alg' => 'none']) . '.' . $b64(['sub' => 'user-sub', 'nonce' => $nonce]) . '.unsigned';
		}

		return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));
	}

	private function userinfoResponse(): Response
	{
		return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['sub' => 'user-sub', 'name' => 'Test User']));
	}

	/** @return array<string, string> */
	private function query(string $url): array
	{
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
		return $query;
	}
}

(new SsoProviderTest)->run();
