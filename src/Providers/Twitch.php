<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;

/**
 * Twitch with OpenID Connect. The exchange needs the client secret and
 * Twitch documents no PKCE, so the backend checks the id_token's nonce.
 */
final class Twitch
{
    public const ISSUER = 'https://id.twitch.tv/oauth2';

    public const AUTHORIZATION_ENDPOINT = 'https://id.twitch.tv/oauth2/authorize';

    public const TOKEN_ENDPOINT = 'https://id.twitch.tv/oauth2/token';

    public const USERINFO_ENDPOINT = 'https://id.twitch.tv/oauth2/userinfo';

    public const JWKS_URI = 'https://id.twitch.tv/oauth2/keys';

    public const VALIDATE_ENDPOINT = 'https://id.twitch.tv/oauth2/validate';

    public const REVOCATION_ENDPOINT = 'https://id.twitch.tv/oauth2/revoke';

    public const DEFAULT_SCOPES = ['openid', 'user:read:email'];

    public const DEFAULT_CLAIMS = ['email', 'email_verified', 'picture', 'preferred_username'];

    /** Value of the `claims` parameter: Twitch only returns `sub` unless asked. */
    public static function claims(array $claims = self::DEFAULT_CLAIMS): string
    {
        $requested = (object) array_fill_keys($claims, null);

        return json_encode(['id_token' => $requested, 'userinfo' => $requested]);
    }

    public static function config(array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'twitch',
            authorizationEndpoint: self::AUTHORIZATION_ENDPOINT,
            defaultScopes: self::DEFAULT_SCOPES,
            issuer: self::ISSUER,
            usePkce: false,
            useNonce: true,
            extraAuthParams: ['claims' => self::claims()],
            mapProfile: self::class.'::mapProfile',
        ))->with($overrides);
    }

    public static function mapProfile(array $raw): array
    {
        return Profile::clean([
            'id' => Profile::id($raw['sub'] ?? ''),
            'username' => Profile::text($raw['preferred_username'] ?? null),
            'picture' => Profile::text($raw['picture'] ?? null),
            'email' => Profile::text($raw['email'] ?? null),
            'emailVerified' => Profile::bool($raw['email_verified'] ?? null),
        ]);
    }
}
