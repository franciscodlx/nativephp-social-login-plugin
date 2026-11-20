<?php

namespace Franciscodlxdev\SocialLogin\Support;

/**
 * PKCE (RFC 7636, S256), `state` and `nonce`. PHP runs on the device, so
 * random_bytes() is the platform CSPRNG.
 */
final class Pkce
{
    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** `$byteLength` random bytes, base64url without padding. 32 bytes → 43 chars. */
    public static function random(int $byteLength = 32): string
    {
        return self::base64Url(random_bytes(max(1, min($byteLength, 1024))));
    }

    /** `BASE64URL(SHA256(verifier))`. */
    public static function challenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }
}
