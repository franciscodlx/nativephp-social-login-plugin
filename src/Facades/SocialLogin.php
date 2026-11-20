<?php

namespace Franciscodlxdev\SocialLogin\Facades;

use Franciscodlxdev\SocialLogin\AuthException;
use Franciscodlxdev\SocialLogin\ProviderConfig;
use Franciscodlxdev\SocialLogin\SignInResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string signIn(ProviderConfig $provider, array $options)
 * @method static SignInResult complete(string $url)
 * @method static AuthException fail(string $code, string $message = '')
 * @method static void cancel()
 * @method static bool isAvailable()
 * @method static void signOut(ProviderConfig $provider, string $clientId, string $token, ?string $tokenTypeHint = null)
 * @method static ProviderConfig linkedin(array $overrides = [])
 * @method static ProviderConfig x(array $overrides = [])
 * @method static ProviderConfig mercadolibre(string $country, array $overrides = [])
 * @method static ProviderConfig twitch(array $overrides = [])
 * @method static ProviderConfig telegram(array $overrides = [])
 * @method static ProviderConfig tiktok(array $overrides = [])
 * @method static ProviderConfig preset(string $name, array $overrides = [])
 *
 * @see \Franciscodlxdev\SocialLogin\SocialLogin
 */
class SocialLogin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Franciscodlxdev\SocialLogin\SocialLogin::class;
    }
}
