<?php

namespace Franciscodlxdev\SocialLogin;

/**
 * Outcome of a sign-in. Without `exchangeCode`, send `code` (plus
 * `codeVerifier` / `nonce`) and `redirectUri` to your backend.
 */
final class SignInResult
{
    /**
     * @param  array<string, string>  $params  every parameter the provider sent back
     * @param  array<string, mixed>|null  $idTokenClaims  unverified (check the signature on your backend)
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>|null  $tokenResponse
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $code,
        public readonly string $state,
        public readonly string $redirectUri,
        public readonly array $params,
        public readonly ?string $codeVerifier = null,
        public readonly ?string $nonce = null,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?string $idToken = null,
        public readonly ?int $expiresIn = null,
        public readonly ?string $scope = null,
        public readonly ?array $idTokenClaims = null,
        public readonly ?array $profile = null,
        public readonly ?array $tokenResponse = null,
    ) {}

    /** What a backend needs to exchange the code. */
    public function forBackend(): array
    {
        return array_filter([
            'code' => $this->code,
            'codeVerifier' => $this->codeVerifier,
            'nonce' => $this->nonce,
            'redirectUri' => $this->redirectUri,
        ], static fn ($value) => $value !== null);
    }
}
