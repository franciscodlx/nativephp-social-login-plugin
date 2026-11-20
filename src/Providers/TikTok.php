<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;
use Franciscodlxdev\SocialLogin\SignInResult;

/**
 * TikTok Login Kit, web flow. TikTok calls the client id "client key", joins
 * scopes with commas and needs the client secret for the exchange.
 */
final class TikTok
{
    public const AUTHORIZATION_ENDPOINT = 'https://www.tiktok.com/v2/auth/authorize/';

    public const TOKEN_ENDPOINT = 'https://open.tiktokapis.com/v2/oauth/token/';

    public const REVOCATION_ENDPOINT = 'https://open.tiktokapis.com/v2/oauth/revoke/';

    public const USERINFO_ENDPOINT = 'https://open.tiktokapis.com/v2/user/info/';

    public const DEFAULT_SCOPES = ['user.info.basic'];

    public const BASIC_FIELDS = ['open_id', 'union_id', 'avatar_url', 'display_name'];

    public const PROFILE_FIELDS = ['username', 'bio_description', 'profile_deep_link', 'is_verified'];

    public static function config(array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'tiktok',
            authorizationEndpoint: self::AUTHORIZATION_ENDPOINT,
            defaultScopes: self::DEFAULT_SCOPES,
            scopeSeparator: ',',
            usePkce: false,
            clientIdParam: 'client_key',
            mapProfile: self::class.'::mapProfile',
        ))->with($overrides);
    }

    public static function userInfoUrl(array $fields = self::BASIC_FIELDS, string $endpoint = self::USERINFO_ENDPOINT): string
    {
        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').'fields='.implode(',', $fields);
    }

    /** `/v2/user/info/` response → profile. */
    public static function mapProfile(array $raw): array
    {
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
        $user = is_array($data['user'] ?? null) ? $data['user'] : $data;

        return Profile::clean([
            'id' => Profile::id($user['open_id'] ?? ''),
            'unionId' => Profile::text($user['union_id'] ?? null),
            'displayName' => Profile::text($user['display_name'] ?? null),
            'picture' => Profile::text($user['avatar_url'] ?? null),
            'username' => Profile::text($user['username'] ?? null),
            'verified' => Profile::bool($user['is_verified'] ?? null),
        ]);
    }

    /** Scopes the user actually granted (TikTok lets them untick some). */
    public static function grantedScopes(SignInResult $result): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $result->params['scopes'] ?? ''))));
    }
}
