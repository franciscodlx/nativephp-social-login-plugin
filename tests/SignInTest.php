<?php

namespace Franciscodlxdev\SocialLogin\Tests;

use Franciscodlxdev\SocialLogin\AuthException;
use Franciscodlxdev\SocialLogin\Facades\SocialLogin;
use Franciscodlxdev\SocialLogin\ProviderConfig;
use Franciscodlxdev\SocialLogin\Support\Pkce;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class SignInTest extends TestCase
{
    private function provider(array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'mock',
            authorizationEndpoint: 'https://idp.test/authorize',
            defaultScopes: ['openid', 'profile'],
            tokenEndpoint: 'https://idp.test/token',
            userInfoEndpoint: 'https://idp.test/userinfo',
            issuer: 'https://idp.test',
            useNonce: true,
            mapProfile: self::class.'::mapProfile',
        ))->with($overrides);
    }

    public static function mapProfile(array $raw): array
    {
        return ['id' => (string) $raw['sub'], 'email' => $raw['email'] ?? null];
    }

    private function signInOptions(array $extra = []): array
    {
        return array_merge(['clientId' => 'client-1', 'redirectUri' => 'myapp://callback'], $extra);
    }

    private function assertAuthError(string $code, callable $run): AuthException
    {
        try {
            $run();
        } catch (AuthException $e) {
            $this->assertSame($code, $e->authCode, $e->getMessage());

            return $e;
        }
        $this->fail("Expected AuthException {$code}");
    }

    public function test_it_sends_state_scopes_nonce_and_a_pkce_s256_challenge(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $sent = $this->bridge->sentParams();

        $this->assertSame('code', $sent['response_type']);
        $this->assertSame('client-1', $sent['client_id']);
        $this->assertSame('myapp://callback', $sent['redirect_uri']);
        $this->assertSame('openid profile', $sent['scope']);
        $this->assertSame('S256', $sent['code_challenge_method']);
        $this->assertSame(22, strlen($sent['state']));
        $this->assertArrayHasKey('nonce', $sent);
        $this->assertSame('myapp://callback', $this->bridge->opened[0]['callbackUri']);

        $result = SocialLogin::complete($this->bridge->approve());
        $this->assertSame('the-code', $result->code);
        $this->assertSame($sent['state'], $result->state);
        $this->assertSame($sent['nonce'], $result->nonce);
        $this->assertSame(Pkce::challenge($result->codeVerifier), $sent['code_challenge']);
        $this->assertSame(43, strlen($result->codeVerifier));
        $this->assertStringNotContainsString($result->codeVerifier, $this->bridge->opened[0]['url']);
    }

    public function test_pkce_and_nonce_follow_the_provider(): void
    {
        SocialLogin::signIn($this->provider(['usePkce' => false, 'useNonce' => false]), $this->signInOptions());
        $sent = $this->bridge->sentParams();
        $this->assertArrayNotHasKey('code_challenge', $sent);
        $this->assertArrayNotHasKey('nonce', $sent);

        $result = SocialLogin::complete($this->bridge->approve());
        $this->assertNull($result->codeVerifier);
        $this->assertSame(['code' => 'the-code', 'redirectUri' => 'myapp://callback'], $result->forBackend());
    }

    public function test_a_backend_redirect_lands_on_the_callback_uri(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions([
            'redirectUri' => 'https://api.example.com/auth/callback',
            'callbackUri' => 'myapp://callback',
        ]));
        $this->assertSame('https://api.example.com/auth/callback', $this->bridge->sentParams()['redirect_uri']);
        $this->assertSame('myapp://callback', $this->bridge->opened[0]['callbackUri']);

        $result = SocialLogin::complete($this->bridge->approve());
        $this->assertSame('https://api.example.com/auth/callback', $result->redirectUri);
    }

    public function test_it_rejects_a_state_it_did_not_start_and_a_replay(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete('myapp://callback?code=x&state=forged'));

        $redirect = $this->bridge->approve();
        SocialLogin::complete($redirect);
        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($redirect));
    }

    public function test_it_rejects_a_redirect_to_another_uri(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $state = $this->bridge->sentParams()['state'];

        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete("other://callback?code=x&state={$state}"));
    }

    public function test_access_denied_and_provider_cancel_errors_are_cancelled(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $this->assertAuthError(AuthException::CANCELLED, fn () => SocialLogin::complete($this->bridge->approve(['error' => 'access_denied'])));

        SocialLogin::signIn($this->provider(['cancelErrors' => ['user_cancelled_login']]), $this->signInOptions());
        $e = $this->assertAuthError(AuthException::CANCELLED, fn () => SocialLogin::complete($this->bridge->approve(['error' => 'user_cancelled_login'])));
        $this->assertSame('user_cancelled_login', $e->providerError);

        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($this->bridge->approve(['error' => 'server_error', 'error_description' => 'Down'])));
    }

    public function test_it_requires_a_code(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($this->bridge->approve([])));
    }

    public function test_invalid_configuration(): void
    {
        $this->assertAuthError(AuthException::INVALID_CONFIG, fn () => SocialLogin::signIn($this->provider(), ['redirectUri' => 'myapp://callback']));
        $this->assertAuthError(AuthException::INVALID_CONFIG, fn () => SocialLogin::signIn($this->provider(['authorizationEndpoint' => 'http://idp.test/authorize']), $this->signInOptions()));
        $this->assertAuthError(AuthException::INVALID_CONFIG, fn () => SocialLogin::signIn($this->provider(), $this->signInOptions(['redirectUri' => 'not a uri'])));
        $this->assertAuthError(AuthException::INVALID_CONFIG, fn () => SocialLogin::signIn($this->provider(['tokenEndpoint' => null]), $this->signInOptions(['exchangeCode' => true])));
        $this->assertSame([], $this->bridge->opened);

        // Plain http is fine towards the local test server.
        SocialLogin::signIn($this->provider(['authorizationEndpoint' => 'http://10.0.2.2:4000/authorize']), $this->signInOptions());
        $this->assertCount(1, $this->bridge->opened);
    }

    public function test_a_native_failure_to_open_is_reported_and_forgotten(): void
    {
        $this->bridge->openError = ['code' => 'INVALID_CONFIG', 'message' => 'No intent-filter for "myapp"'];
        $this->assertAuthError(AuthException::INVALID_CONFIG, fn () => SocialLogin::signIn($this->provider(), $this->signInOptions()));

        $state = $this->bridge->sentParams()['state'];
        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete("myapp://callback?code=x&state={$state}"));
    }

    public function test_fail_turns_the_native_error_into_an_auth_exception_and_clears_the_sign_in(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $redirect = $this->bridge->approve();

        $error = SocialLogin::fail('CANCELLED', 'The user closed the browser');
        $this->assertSame(AuthException::CANCELLED, $error->authCode);
        $this->assertSame('The user closed the browser', $error->getMessage());
        $this->assertSame(AuthException::PROVIDER_ERROR, SocialLogin::fail('WEIRD', '')->authCode);

        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($redirect));
    }

    public function test_a_new_sign_in_replaces_an_abandoned_one(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions());
        $abandoned = $this->bridge->approve();
        SocialLogin::signIn($this->provider(), $this->signInOptions());

        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($abandoned));
        $this->assertSame('the-code', SocialLogin::complete($this->bridge->approve())->code);
    }

    public function test_exchange_code_on_device_validates_the_id_token_and_loads_the_profile(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions(['exchangeCode' => true]));
        $nonce = $this->bridge->sentParams()['nonce'];
        $idToken = self::jwt(['iss' => 'https://idp.test', 'aud' => 'client-1', 'sub' => 'u1', 'nonce' => $nonce, 'exp' => time() + 300]);
        Http::fake([
            'idp.test/token' => Http::response(['access_token' => 'at', 'refresh_token' => 'rt', 'id_token' => $idToken, 'expires_in' => '3600']),
            'idp.test/userinfo' => Http::response(['sub' => 'u1', 'email' => 'ada@example.com']),
        ]);

        $result = SocialLogin::complete($this->bridge->approve());

        $this->assertSame('at', $result->accessToken);
        $this->assertSame('rt', $result->refreshToken);
        $this->assertSame(3600, $result->expiresIn);
        $this->assertSame(['id' => 'u1', 'email' => 'ada@example.com'], $result->profile);
        Http::assertSent(function (Request $request) use ($result) {
            return $request->url() === 'https://idp.test/token'
                && $request['code_verifier'] === $result->codeVerifier
                && $request['client_id'] === 'client-1'
                && ! isset($request['client_secret']);
        });
    }

    public function test_exchange_rejects_an_id_token_with_another_nonce(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions(['exchangeCode' => true]));
        Http::fake(['idp.test/token' => Http::response([
            'access_token' => 'at',
            'id_token' => self::jwt(['iss' => 'https://idp.test', 'aud' => 'client-1', 'nonce' => 'other']),
        ])]);

        $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($this->bridge->approve()));
    }

    public function test_exchange_reports_problem_details_and_network_errors(): void
    {
        SocialLogin::signIn($this->provider(), $this->signInOptions(['exchangeCode' => true]));
        Http::fake(['idp.test/token' => Http::response(['title' => 'Client Forbidden', 'detail' => 'Not enrolled', 'reason' => 'client-not-enrolled'], 403)]);
        $e = $this->assertAuthError(AuthException::PROVIDER_ERROR, fn () => SocialLogin::complete($this->bridge->approve()));
        $this->assertSame('client-not-enrolled', $e->providerError);
        $this->assertSame('Not enrolled', $e->getMessage());
        $this->assertSame(403, $e->status);

        SocialLogin::signIn($this->provider(), $this->signInOptions(['exchangeCode' => true]));
        Http::fake(fn () => throw new ConnectionException('down'));
        $this->assertAuthError(AuthException::NETWORK, fn () => SocialLogin::complete($this->bridge->approve()));
    }

    public function test_cancel_reaches_the_native_side(): void
    {
        SocialLogin::cancel();
        $this->assertTrue($this->bridge->cancelled);
    }

    public static function jwt(array $claims): string
    {
        $encode = fn ($value) => Pkce::base64Url(json_encode($value));

        return $encode(['alg' => 'RS256']).'.'.$encode($claims).'.signature';
    }
}
