<?php

namespace Franciscodlxdev\SocialLogin\Support;

use Franciscodlxdev\SocialLogin\AuthException;
use JsonException;

final class IdToken
{
    /**
     * Payload of a JWT **without verifying its signature**. Use the claims for
     * display; verify the signature on your backend before trusting them.
     *
     * @return array<string, mixed>
     */
    public static function decode(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3 || $parts[1] === '') {
            throw new AuthException(AuthException::PROVIDER_ERROR, 'Malformed id_token');
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        try {
            $claims = $json === false ? null : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AuthException(AuthException::PROVIDER_ERROR, 'Malformed id_token', previous: $e);
        }
        if (! is_array($claims) || array_is_list($claims)) {
            throw new AuthException(AuthException::PROVIDER_ERROR, 'Malformed id_token');
        }

        return $claims;
    }

    /**
     * Checks what an app can check on-device: `iss`, `aud`, `exp` and `nonce`
     * (OpenID Connect Core §3.1.3.7). The signature is NOT verified here.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function validate(
        array $claims,
        ?string $issuer = null,
        ?string $audience = null,
        ?string $nonce = null,
        int $clockToleranceSeconds = 60,
        ?int $now = null,
    ): void {
        $fail = static fn (string $reason) => throw new AuthException(
            AuthException::PROVIDER_ERROR,
            "Invalid id_token: {$reason}",
        );
        if ($issuer !== null && ($claims['iss'] ?? null) !== $issuer) {
            $fail('unexpected issuer');
        }
        if ($audience !== null) {
            $aud = $claims['aud'] ?? null;
            if (! in_array($audience, is_array($aud) ? $aud : [$aud], true)) {
                $fail('unexpected audience');
            }
        }
        if ($nonce !== null && ($claims['nonce'] ?? null) !== $nonce) {
            $fail('nonce mismatch');
        }
        $exp = $claims['exp'] ?? null;
        if (is_int($exp) || is_float($exp)) {
            if (($now ?? time()) > $exp + $clockToleranceSeconds) {
                $fail('token expired');
            }
        }
    }
}
