<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;

/**
 * Sign In with LinkedIn using OpenID Connect. The token request needs the
 * client secret, so the code is exchanged by your backend.
 */
final class LinkedIn
{
    public const ISSUER = 'https://www.linkedin.com/oauth';

    public const AUTHORIZATION_ENDPOINT = 'https://www.linkedin.com/oauth/v2/authorization';

    /** For your backend: LinkedIn requires the client secret here. */
    public const TOKEN_ENDPOINT = 'https://www.linkedin.com/oauth/v2/accessToken';

    public const USERINFO_ENDPOINT = 'https://api.linkedin.com/v2/userinfo';

    public const JWKS_URI = 'https://www.linkedin.com/oauth/openid/jwks';

    public const DEFAULT_SCOPES = ['openid', 'profile', 'email'];

    public static function config(array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'linkedin',
            authorizationEndpoint: self::AUTHORIZATION_ENDPOINT,
            defaultScopes: self::DEFAULT_SCOPES,
            issuer: self::ISSUER,
            // LinkedIn documents neither PKCE nor nonce for this flow; state is
            // still generated and verified.
            usePkce: false,
            cancelErrors: ['user_cancelled_login', 'user_cancelled_authorize'],
            mapProfile: self::class.'::mapProfile',
        ))->with($overrides);
    }

    /** Userinfo response (or id_token claims) → profile. */
    public static function mapProfile(array $raw): array
    {
        $locale = $raw['locale'] ?? null;
        if (is_array($locale)) {
            // LinkedIn has also returned { language: "es", country: "MX" }.
            $parts = array_filter([Profile::text($locale['language'] ?? null), Profile::text($locale['country'] ?? null)]);
            $locale = $parts === [] ? null : implode('-', $parts);
        }
        $verified = $raw['email_verified'] ?? null;

        return Profile::clean([
            'id' => Profile::id($raw['sub'] ?? ''),
            'name' => Profile::text($raw['name'] ?? null),
            'givenName' => Profile::text($raw['given_name'] ?? null),
            'familyName' => Profile::text($raw['family_name'] ?? null),
            'picture' => Profile::text($raw['picture'] ?? null),
            'locale' => Profile::text($locale),
            'email' => Profile::text($raw['email'] ?? null),
            'emailVerified' => is_bool($verified) ? $verified : ($verified === 'true' ? true : null),
        ]);
    }
}
