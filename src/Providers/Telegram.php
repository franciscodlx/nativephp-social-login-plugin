<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;

/**
 * Log in with Telegram (OpenID Connect + PKCE). The token request is
 * authenticated with the client secret, so it goes on your backend.
 */
final class Telegram
{
    public const ISSUER = 'https://oauth.telegram.org';

    public const AUTHORIZATION_ENDPOINT = 'https://oauth.telegram.org/auth';

    public const TOKEN_ENDPOINT = 'https://oauth.telegram.org/token';

    public const JWKS_URI = 'https://oauth.telegram.org/.well-known/jwks.json';

    public const DEFAULT_SCOPES = ['openid', 'profile'];

    /** Adds `phone_number`; the user is asked for consent. */
    public const PHONE_SCOPE = 'phone';

    /** Lets your bot message the user after the login. */
    public const BOT_ACCESS_SCOPE = 'telegram:bot_access';

    public static function config(array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'telegram',
            authorizationEndpoint: self::AUTHORIZATION_ENDPOINT,
            defaultScopes: self::DEFAULT_SCOPES,
            issuer: self::ISSUER,
            mapProfile: self::class.'::mapProfile',
        ))->with($overrides);
    }

    /** id_token claims → profile. */
    public static function mapProfile(array $raw): array
    {
        $telegramId = $raw['id'] ?? null;

        return Profile::clean([
            'id' => Profile::id($raw['sub'] ?? ''),
            'telegramId' => is_int($telegramId) || is_string($telegramId) ? (string) $telegramId : null,
            'name' => Profile::text($raw['name'] ?? null),
            'username' => Profile::text($raw['preferred_username'] ?? null),
            'picture' => Profile::text($raw['picture'] ?? null),
            'phoneNumber' => Profile::text($raw['phone_number'] ?? null),
        ]);
    }
}
