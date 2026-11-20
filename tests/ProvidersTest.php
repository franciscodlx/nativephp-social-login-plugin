<?php

namespace Franciscodlxdev\SocialLogin\Tests;

use Franciscodlxdev\SocialLogin\AuthException;
use Franciscodlxdev\SocialLogin\Facades\SocialLogin;
use Franciscodlxdev\SocialLogin\Providers\LinkedIn;
use Franciscodlxdev\SocialLogin\Providers\MercadoLibre;
use Franciscodlxdev\SocialLogin\Providers\Presets;
use Franciscodlxdev\SocialLogin\Providers\Telegram;
use Franciscodlxdev\SocialLogin\Providers\TikTok;
use Franciscodlxdev\SocialLogin\Providers\Twitch;
use Franciscodlxdev\SocialLogin\Providers\X;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ProvidersTest extends TestCase
{
    private const BACKEND = ['clientId' => 'client', 'redirectUri' => 'https://api.example.com/cb', 'callbackUri' => 'myapp://callback'];

    public function test_linkedin_uses_state_only_and_maps_userinfo(): void
    {
        SocialLogin::signIn(SocialLogin::linkedin(), self::BACKEND);
        $sent = $this->bridge->sentParams();
        $this->assertStringStartsWith(LinkedIn::AUTHORIZATION_ENDPOINT.'?', $this->bridge->opened[0]['url']);
        $this->assertSame('openid profile email', $sent['scope']);
        $this->assertArrayNotHasKey('code_challenge', $sent);

        $this->assertSame([
            'id' => 'abc',
            'name' => 'Ada Lovelace',
            'locale' => 'es-MX',
            'email' => 'ada@example.com',
            'emailVerified' => true,
        ], LinkedIn::mapProfile([
            'sub' => 'abc', 'name' => 'Ada Lovelace', 'locale' => ['language' => 'es', 'country' => 'MX'],
            'email' => 'ada@example.com', 'email_verified' => 'true',
        ]));
    }

    public function test_linkedin_user_cancel_is_cancelled(): void
    {
        SocialLogin::signIn(SocialLogin::linkedin(), self::BACKEND);
        try {
            SocialLogin::complete($this->bridge->approve(['error' => 'user_cancelled_authorize']));
            $this->fail('Expected CANCELLED');
        } catch (AuthException $e) {
            $this->assertSame(AuthException::CANCELLED, $e->authCode);
        }
    }

    public function test_x_is_a_public_client_and_asks_for_the_email_field_with_its_scope(): void
    {
        SocialLogin::signIn(SocialLogin::x(), ['clientId' => 'x-client', 'redirectUri' => 'myapp://callback', 'exchangeCode' => true, 'scopes' => ['users.read', 'tweet.read', 'users.email']]);
        $this->assertSame('S256', $this->bridge->sentParams()['code_challenge_method']);
        Http::fake([
            'api.x.com/2/oauth2/token' => Http::response(['access_token' => 'at', 'token_type' => 'bearer']),
            'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '42', 'username' => 'ada', 'name' => 'Ada', 'confirmed_email' => 'ada@example.com']]),
        ]);

        $result = SocialLogin::complete($this->bridge->approve());

        $this->assertSame(['id' => '42', 'username' => 'ada', 'name' => 'Ada', 'email' => 'ada@example.com'], $result->profile);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'user.fields=id,name,username,profile_image_url,verified,confirmed_email'));
        $this->assertSame('https://h/2/users/me?x=1&user.fields=id,name,username,profile_image_url,verified', X::userInfoUrl('https://h/2/users/me?x=1'));
    }

    public function test_mercadolibre_signs_in_on_the_country_host(): void
    {
        SocialLogin::signIn(SocialLogin::mercadolibre('mx'), self::BACKEND);
        $this->assertStringStartsWith('https://auth.mercadolibre.com.mx/authorization?', $this->bridge->opened[0]['url']);
        $this->assertArrayNotHasKey('scope', $this->bridge->sentParams());
        $this->assertSame('https://auth.mercadolivre.com.br/authorization', MercadoLibre::authorizationEndpoint('BR'));
        $this->assertSame('ada', MercadoLibre::mapProfile(['id' => 1, 'nickname' => 'ada', 'thumbnail' => ['picture_url' => 'p']])['nickname']);

        $this->expectException(InvalidArgumentException::class);
        MercadoLibre::config('XX');
    }

    public function test_twitch_sends_a_nonce_and_the_claims_parameter(): void
    {
        SocialLogin::signIn(SocialLogin::twitch(), self::BACKEND);
        $sent = $this->bridge->sentParams();
        $this->assertArrayHasKey('nonce', $sent);
        $this->assertArrayNotHasKey('code_challenge', $sent);
        $this->assertSame(
            ['id_token' => ['email' => null, 'email_verified' => null, 'picture' => null, 'preferred_username' => null], 'userinfo' => ['email' => null, 'email_verified' => null, 'picture' => null, 'preferred_username' => null]],
            json_decode($sent['claims'], true),
        );
        $this->assertSame('{"id_token":{"picture":null},"userinfo":{"picture":null}}', Twitch::claims(['picture']));
    }

    public function test_telegram_uses_pkce_and_maps_claims(): void
    {
        SocialLogin::signIn(SocialLogin::telegram(), self::BACKEND + ['scopes' => ['openid', 'profile', Telegram::PHONE_SCOPE]]);
        $this->assertSame('openid profile phone', $this->bridge->sentParams()['scope']);
        $this->assertSame('S256', $this->bridge->sentParams()['code_challenge_method']);
        $this->assertSame(
            ['id' => 's', 'telegramId' => '123', 'username' => 'ada', 'phoneNumber' => '+52'],
            Telegram::mapProfile(['sub' => 's', 'id' => 123, 'preferred_username' => 'ada', 'phone_number' => '+52']),
        );
    }

    public function test_tiktok_uses_client_key_and_comma_separated_scopes(): void
    {
        SocialLogin::signIn(SocialLogin::tiktok(), self::BACKEND + ['scopes' => ['user.info.basic', 'user.info.profile']]);
        $sent = $this->bridge->sentParams();
        $this->assertSame('client', $sent['client_key']);
        $this->assertArrayNotHasKey('client_id', $sent);
        $this->assertSame('user.info.basic,user.info.profile', $sent['scope']);

        $result = SocialLogin::complete($this->bridge->approve(['code' => 'c', 'scopes' => 'user.info.basic, user.info.profile']));
        $this->assertSame(['user.info.basic', 'user.info.profile'], TikTok::grantedScopes($result));
        $this->assertSame('open-1', TikTok::mapProfile(['data' => ['user' => ['open_id' => 'open-1']]])['id']);
    }

    public function test_presets(): void
    {
        SocialLogin::signIn(SocialLogin::preset('discord'), self::BACKEND);
        try {
            SocialLogin::complete($this->bridge->approve(['code' => 'c', 'iss' => 'https://evil.example']));
            $this->fail('Expected an issuer error');
        } catch (AuthException $e) {
            $this->assertSame(AuthException::PROVIDER_ERROR, $e->authCode);
        }
        SocialLogin::signIn(SocialLogin::preset('discord'), self::BACKEND);
        $this->assertSame('c', SocialLogin::complete($this->bridge->approve(['code' => 'c', 'iss' => 'https://discord.com']))->code);

        SocialLogin::signIn(SocialLogin::preset('strava'), self::BACKEND);
        $this->assertSame('auto', $this->bridge->sentParams()['approval_prompt']);
        $this->assertSame('Ada Lovelace', Presets::mapStravaProfile(['id' => 1, 'firstname' => 'Ada', 'lastname' => 'Lovelace'])['name']);
        $this->assertSame('https://cdn.discordapp.com/avatars/7/av.png', Presets::mapDiscordProfile(['id' => '7', 'avatar' => 'av'])['picture']);

        SocialLogin::signIn(SocialLogin::preset('slack'), self::BACKEND);
        $this->assertArrayHasKey('nonce', $this->bridge->sentParams());

        $this->expectException(InvalidArgumentException::class);
        Presets::config('myspace');
    }
}
