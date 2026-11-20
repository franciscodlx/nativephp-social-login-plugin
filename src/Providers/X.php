<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;
use Franciscodlxdev\SocialLogin\Support\TokenClient;

/**
 * X (Twitter) with OAuth 2.0 + PKCE as a public client: the app can exchange
 * the code itself (`exchangeCode`), no consumer secret involved.
 */
final class X
{
    public const AUTHORIZATION_ENDPOINT = 'https://x.com/i/oauth2/authorize';

    public const TOKEN_ENDPOINT = 'https://api.x.com/2/oauth2/token';

    public const REVOCATION_ENDPOINT = 'https://api.x.com/2/oauth2/revoke';

    public const USERINFO_ENDPOINT = 'https://api.x.com/2/users/me';

    /** What `GET /2/users/me` needs. Add `offline.access` for a refresh token. */
    public const DEFAULT_SCOPES = ['users.read', 'tweet.read'];

    public const OFFLINE_SCOPE = 'offline.access';

    public const EMAIL_SCOPE = 'users.email';

    private const BASE_USER_FIELDS = ['id', 'name', 'username', 'profile_image_url', 'verified'];

    public static function config(array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'x',
            authorizationEndpoint: self::AUTHORIZATION_ENDPOINT,
            defaultScopes: self::DEFAULT_SCOPES,
            tokenEndpoint: self::TOKEN_ENDPOINT,
            userInfoEndpoint: self::USERINFO_ENDPOINT,
            revocationEndpoint: self::REVOCATION_ENDPOINT,
            mapProfile: self::class.'::mapProfile',
            userInfoUrl: self::class.'::userInfoUrl',
        ))->with($overrides);
    }

    /** `/2/users/me` asking for the fields the granted scopes allow. */
    public static function userInfoUrl(string $endpoint = self::USERINFO_ENDPOINT, array $scopes = self::DEFAULT_SCOPES): string
    {
        $fields = in_array(self::EMAIL_SCOPE, $scopes, true)
            ? [...self::BASE_USER_FIELDS, 'confirmed_email']
            : self::BASE_USER_FIELDS;

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').'user.fields='.implode(',', $fields);
    }

    /** `GET /2/users/me` response (`{ data: {...} }`) → profile. */
    public static function mapProfile(array $raw): array
    {
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;

        return Profile::clean([
            'id' => Profile::id($data['id'] ?? ''),
            'username' => Profile::id($data['username'] ?? ''),
            'name' => Profile::text($data['name'] ?? null),
            'picture' => Profile::text($data['profile_image_url'] ?? null),
            'verified' => Profile::bool($data['verified'] ?? null),
            'email' => Profile::text($data['confirmed_email'] ?? null),
        ]);
    }

    /**
     * New access token from a refresh token (needs `offline.access`). Store
     * the refresh token that comes back: the old one stops working.
     *
     * @return array<string, mixed>
     */
    public static function refresh(string $clientId, string $refreshToken, string $tokenEndpoint = self::TOKEN_ENDPOINT): array
    {
        return TokenClient::refresh($tokenEndpoint, $clientId, $refreshToken);
    }
}
