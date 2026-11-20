<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;
use InvalidArgumentException;

/**
 * Mercado Libre with OAuth 2.0 + PKCE. Each account belongs to one site: the
 * user must sign in on the host of their country. The exchange needs the
 * client secret, so it goes on your backend.
 */
final class MercadoLibre
{
    public const AUTH_HOSTS = [
        'AR' => 'auth.mercadolibre.com.ar',
        'BO' => 'auth.mercadolibre.com.bo',
        'BR' => 'auth.mercadolivre.com.br',
        'CL' => 'auth.mercadolibre.cl',
        'CO' => 'auth.mercadolibre.com.co',
        'CR' => 'auth.mercadolibre.co.cr',
        'DO' => 'auth.mercadolibre.com.do',
        'EC' => 'auth.mercadolibre.com.ec',
        'MX' => 'auth.mercadolibre.com.mx',
        'PA' => 'auth.mercadolibre.com.pa',
        'PE' => 'auth.mercadolibre.com.pe',
        'PY' => 'auth.mercadolibre.com.py',
        'UY' => 'auth.mercadolibre.com.uy',
        'VE' => 'auth.mercadolibre.com.ve',
    ];

    public const TOKEN_ENDPOINT = 'https://api.mercadolibre.com/oauth/token';

    public const USERINFO_ENDPOINT = 'https://api.mercadolibre.com/users/me';

    public static function authorizationEndpoint(string $country): string
    {
        $host = self::AUTH_HOSTS[strtoupper($country)] ?? throw new InvalidArgumentException("Unknown Mercado Libre country \"{$country}\"");

        return "https://{$host}/authorization";
    }

    public static function config(string $country, array $overrides = []): ProviderConfig
    {
        return (new ProviderConfig(
            id: 'mercadolibre',
            authorizationEndpoint: self::authorizationEndpoint($country),
            mapProfile: self::class.'::mapProfile',
        ))->with($overrides);
    }

    /** `/users/me` response → profile. */
    public static function mapProfile(array $raw): array
    {
        $thumbnail = is_array($raw['thumbnail'] ?? null) ? $raw['thumbnail'] : [];

        return Profile::clean([
            'id' => Profile::id($raw['id'] ?? ''),
            'nickname' => Profile::text($raw['nickname'] ?? null),
            'firstName' => Profile::text($raw['first_name'] ?? null),
            'lastName' => Profile::text($raw['last_name'] ?? null),
            'email' => Profile::text($raw['email'] ?? null),
            'countryId' => Profile::text($raw['country_id'] ?? null),
            'siteId' => Profile::text($raw['site_id'] ?? null),
            'picture' => Profile::text($thumbnail['picture_url'] ?? null),
        ]);
    }
}
