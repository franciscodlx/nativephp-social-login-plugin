<?php

namespace Franciscodlxdev\SocialLogin\Providers;

use Franciscodlxdev\SocialLogin\ProviderConfig;
use InvalidArgumentException;

/**
 * GitHub, Discord, Slack and Strava. All four need the client secret to
 * exchange the code, so the exchange goes on your backend; BACKEND lists the
 * endpoints it calls.
 */
final class Presets
{
    public const NAMES = ['github', 'discord', 'slack', 'strava'];

    public const BACKEND = [
        'github' => [
            'tokenEndpoint' => 'https://github.com/login/oauth/access_token',
            'userInfoEndpoint' => 'https://api.github.com/user',
        ],
        'discord' => [
            'tokenEndpoint' => 'https://discord.com/api/oauth2/token',
            'userInfoEndpoint' => 'https://discord.com/api/users/@me',
            'revocationEndpoint' => 'https://discord.com/api/oauth2/token/revoke',
        ],
        'slack' => [
            'tokenEndpoint' => 'https://slack.com/api/openid.connect.token',
            'userInfoEndpoint' => 'https://slack.com/api/openid.connect.userInfo',
            'jwksUri' => 'https://slack.com/openid/connect/keys',
        ],
        'strava' => [
            'tokenEndpoint' => 'https://www.strava.com/oauth/token',
            'revocationEndpoint' => 'https://www.strava.com/oauth/revoke',
        ],
    ];

    public static function config(string $name, array $overrides = []): ProviderConfig
    {
        $config = match ($name) {
            'github' => new ProviderConfig(
                id: 'github',
                authorizationEndpoint: 'https://github.com/login/oauth/authorize',
                defaultScopes: ['read:user', 'user:email'],
                mapProfile: self::class.'::mapGitHubProfile',
            ),
            'discord' => new ProviderConfig(
                id: 'discord',
                authorizationEndpoint: 'https://discord.com/oauth2/authorize',
                defaultScopes: ['identify', 'email'],
                mapProfile: self::class.'::mapDiscordProfile',
                redirectIssuer: 'https://discord.com',
            ),
            'slack' => new ProviderConfig(
                id: 'slack',
                authorizationEndpoint: 'https://slack.com/openid/connect/authorize',
                defaultScopes: ['openid', 'profile', 'email'],
                issuer: 'https://slack.com',
                usePkce: false,
                useNonce: true,
                mapProfile: self::class.'::mapSlackProfile',
            ),
            'strava' => new ProviderConfig(
                id: 'strava',
                authorizationEndpoint: 'https://www.strava.com/oauth/mobile/authorize',
                defaultScopes: ['read'],
                scopeSeparator: ',',
                usePkce: false,
                extraAuthParams: ['approval_prompt' => 'auto'],
                mapProfile: self::class.'::mapStravaProfile',
            ),
            default => throw new InvalidArgumentException("Unknown preset \"{$name}\""),
        };

        return $config->with($overrides);
    }

    public static function mapGitHubProfile(array $raw): array
    {
        return Profile::clean([
            'id' => Profile::id($raw['id'] ?? ''),
            'username' => Profile::text($raw['login'] ?? null),
            'name' => Profile::text($raw['name'] ?? null),
            'email' => Profile::text($raw['email'] ?? null),
            'picture' => Profile::text($raw['avatar_url'] ?? null),
        ]);
    }

    public static function mapDiscordProfile(array $raw): array
    {
        $id = Profile::id($raw['id'] ?? '');
        $avatar = Profile::text($raw['avatar'] ?? null);

        return Profile::clean([
            'id' => $id,
            'username' => Profile::text($raw['username'] ?? null),
            'name' => Profile::text($raw['global_name'] ?? null),
            'email' => Profile::text($raw['email'] ?? null),
            'emailVerified' => Profile::bool($raw['verified'] ?? null),
            'picture' => $avatar !== null ? "https://cdn.discordapp.com/avatars/{$id}/{$avatar}.png" : null,
        ]);
    }

    public static function mapSlackProfile(array $raw): array
    {
        return Profile::clean([
            'id' => Profile::id($raw['sub'] ?? ''),
            'name' => Profile::text($raw['name'] ?? null),
            'email' => Profile::text($raw['email'] ?? null),
            'emailVerified' => Profile::bool($raw['email_verified'] ?? null),
            'picture' => Profile::text($raw['picture'] ?? null),
        ]);
    }

    public static function mapStravaProfile(array $raw): array
    {
        $name = implode(' ', array_filter([Profile::text($raw['firstname'] ?? null), Profile::text($raw['lastname'] ?? null)]));

        return Profile::clean([
            'id' => Profile::id($raw['id'] ?? ''),
            'username' => Profile::text($raw['username'] ?? null),
            'name' => $name !== '' ? $name : null,
            'picture' => Profile::text($raw['profile'] ?? null),
        ]);
    }
}
