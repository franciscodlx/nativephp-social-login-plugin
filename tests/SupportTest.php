<?php

namespace Franciscodlxdev\SocialLogin\Tests;

use Franciscodlxdev\SocialLogin\AuthException;
use Franciscodlxdev\SocialLogin\Commands\AndroidRedirectSchemes;
use Franciscodlxdev\SocialLogin\Support\IdToken;
use Franciscodlxdev\SocialLogin\Support\Pkce;
use Franciscodlxdev\SocialLogin\Support\Url;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_pkce_challenge_is_base64url_sha256(): void
    {
        $verifier = Pkce::random();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $verifier);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), Pkce::challenge($verifier));
        $this->assertNotSame(Pkce::random(), Pkce::random());
    }

    public function test_url_helpers(): void
    {
        $this->assertSame('https://a/b?x=1&y=a%20b', Url::appendParams('https://a/b?x=1', ['y' => 'a b', 'z' => null]));
        $this->assertEquals(['code' => 'c', 'state' => 's'], Url::redirectParams('myapp://cb?code=c#state=s'));
        $this->assertTrue(Url::matchesRedirectUri('MyApp://Callback/?code=1', 'myapp://callback'));
        $this->assertFalse(Url::matchesRedirectUri('myapp://other?code=1', 'myapp://callback'));
        $this->assertTrue(Url::isSecureEndpoint('https://idp.test'));
        $this->assertTrue(Url::isSecureEndpoint('http://10.0.2.2:4000/authorize'));
        $this->assertFalse(Url::isSecureEndpoint('http://idp.test/authorize'));
        $this->assertFalse(Url::isSecureEndpoint('http://localhost.evil.com/authorize'));
    }

    public function test_id_token_decode_and_validation(): void
    {
        $claims = IdToken::decode(SignInTest::jwt(['iss' => 'i', 'aud' => ['a', 'b'], 'nonce' => 'n', 'exp' => 100]));
        IdToken::validate($claims, 'i', 'b', 'n', now: 150);

        foreach ([['issuer' => 'x'], ['audience' => 'c'], ['nonce' => 'm'], ['now' => 1000]] as $bad) {
            try {
                IdToken::validate($claims, ...$bad);
                $this->fail('Expected an invalid id_token');
            } catch (AuthException $e) {
                $this->assertSame(AuthException::PROVIDER_ERROR, $e->authCode);
            }
        }
        $this->expectException(AuthException::class);
        IdToken::decode('not-a-jwt');
    }

    public function test_android_hook_adds_the_schemes_to_the_redirect_activity(): void
    {
        $manifest = <<<'XML'
<manifest>
    <application>
        <activity
            android:name="dev.franciscodlx.nativephp.sociallogin.AuthRedirectActivity"
            android:theme="@android:style/Theme.Translucent.NoTitleBar"
            android:exported="true" />
    </application>
</manifest>
XML;
        $once = AndroidRedirectSchemes::ensureQueries(AndroidRedirectSchemes::inject($manifest, ['myapp', 'other']));
        $this->assertStringContainsString('<data android:scheme="myapp" />', $once);
        $this->assertStringContainsString('<data android:scheme="other" />', $once);
        $this->assertSame(1, substr_count($once, 'AuthRedirectActivity'));
        $this->assertSame(1, substr_count($once, '<queries>'));

        // Rebuilds replace the previous injection instead of stacking another.
        $twice = AndroidRedirectSchemes::ensureQueries(AndroidRedirectSchemes::inject($once, ['third']));
        $this->assertSame(1, substr_count($twice, 'AuthRedirectActivity'));
        $this->assertStringNotContainsString('android:scheme="myapp"', $twice);
        $this->assertSame(1, substr_count($twice, '<queries>'));
    }
}
