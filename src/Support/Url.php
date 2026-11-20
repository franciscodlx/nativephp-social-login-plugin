<?php

namespace Franciscodlxdev\SocialLogin\Support;

/**
 * URL helpers that treat custom schemes (myapp://callback) the same as https.
 */
final class Url
{
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '10.0.2.2', '[::1]'];

    /** @param array<string, string|null> $params */
    public static function encodeParams(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $pairs[] = rawurlencode((string) $key).'='.rawurlencode($value);
            }
        }

        return implode('&', $pairs);
    }

    /** @return array<string, string> */
    public static function decodeParams(string $query): array
    {
        $out = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            $eq = strpos($pair, '=');
            $key = urldecode($eq === false ? $pair : substr($pair, 0, $eq));
            $out[$key] = $eq === false ? '' : urldecode(substr($pair, $eq + 1));
        }

        return $out;
    }

    /**
     * Appends `$params` to `$endpoint`, keeping any query string it already has.
     *
     * @param  array<string, string|null>  $params
     */
    public static function appendParams(string $endpoint, array $params): string
    {
        $query = self::encodeParams($params);
        if ($query === '') {
            return $endpoint;
        }
        $hashAt = strpos($endpoint, '#');
        $base = $hashAt === false ? $endpoint : substr($endpoint, 0, $hashAt);
        $separator = ! str_contains($base, '?') ? '?' : (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&');

        return $base.$separator.$query;
    }

    /**
     * Query and fragment parameters of a redirect URL (query wins on conflict).
     *
     * @return array<string, string>
     */
    public static function redirectParams(string $url): array
    {
        $hashAt = strpos($url, '#');
        $fragment = $hashAt === false ? '' : substr($url, $hashAt + 1);
        $withoutFragment = $hashAt === false ? $url : substr($url, 0, $hashAt);
        $queryAt = strpos($withoutFragment, '?');
        $query = $queryAt === false ? '' : substr($withoutFragment, $queryAt + 1);

        return array_merge(self::decodeParams($fragment), self::decodeParams($query));
    }

    /** `scheme://host/path` without query, fragment or trailing slash; scheme and host lowercased. */
    public static function normalize(string $url): string
    {
        $base = preg_split('/[?#]/', $url, 2)[0];
        if (! preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):(\/\/[^\/]*)?(.*)$/', $base, $m)) {
            return rtrim($base, '/');
        }

        return rtrim(strtolower($m[1]).':'.strtolower($m[2] ?? '').($m[3] ?? ''), '/');
    }

    /** Whether `$url` is a redirect to `$redirectUri` (same scheme, host and path). */
    public static function matchesRedirectUri(string $url, string $redirectUri): bool
    {
        return self::normalize($url) === self::normalize($redirectUri);
    }

    public static function scheme(string $url): ?string
    {
        return preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $url, $m) ? strtolower($m[1]) : null;
    }

    /**
     * https, or plain http to a loopback host (`10.0.2.2` is the Android
     * emulator's alias for the host machine) so a local test server can be used.
     */
    public static function isSecureEndpoint(string $url): bool
    {
        if (! preg_match('/^(https?):\/\/([^\/?#]+)/i', $url, $m)) {
            return false;
        }
        if (strtolower($m[1]) === 'https') {
            return true;
        }
        $host = $m[2];
        $at = strrpos($host, '@');
        $host = strtolower(preg_replace('/:\d+$/', '', $at === false ? $host : substr($host, $at + 1)));

        return in_array($host, self::LOOPBACK_HOSTS, true);
    }
}
