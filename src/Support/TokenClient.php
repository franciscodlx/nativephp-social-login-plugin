<?php

namespace Franciscodlxdev\SocialLogin\Support;

use Franciscodlxdev\SocialLogin\AuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Public-client calls to a provider (no client secret). When the provider
 * needs a secret, the exchange belongs on your backend instead.
 */
final class TokenClient
{
    /**
     * @param  array<string, string>  $extraParams
     * @return array<string, mixed> raw token response
     */
    public static function exchangeCode(
        string $tokenEndpoint,
        string $clientId,
        string $code,
        string $redirectUri,
        ?string $codeVerifier = null,
        array $extraParams = [],
        string $clientIdParam = 'client_id',
    ): array {
        return self::postForm($tokenEndpoint, array_merge($extraParams, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            $clientIdParam => $clientId,
            'code_verifier' => $codeVerifier,
        ]));
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed>
     */
    public static function refresh(
        string $tokenEndpoint,
        string $clientId,
        string $refreshToken,
        array $scopes = [],
        string $clientIdParam = 'client_id',
    ): array {
        return self::postForm($tokenEndpoint, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            $clientIdParam => $clientId,
            'scope' => $scopes === [] ? null : implode(' ', $scopes),
        ]);
    }

    /** RFC 7009 revocation as a public client. */
    public static function revoke(string $revocationEndpoint, string $clientId, string $token, ?string $tokenTypeHint = null): void
    {
        self::postForm($revocationEndpoint, [
            'token' => $token,
            'token_type_hint' => $tokenTypeHint,
            'client_id' => $clientId,
        ]);
    }

    /** @return array<string, mixed> */
    public static function userInfo(string $endpoint, string $accessToken): array
    {
        return self::send(fn (PendingRequest $http) => $http->withToken($accessToken)->get($endpoint), $endpoint);
    }

    /**
     * @param  array<string, string|null>  $form
     * @return array<string, mixed>
     */
    private static function postForm(string $url, array $form): array
    {
        $form = array_filter($form, static fn ($value) => $value !== null);

        return self::send(fn (PendingRequest $http) => $http->asForm()->post($url, $form), $url);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private static function send(callable $request, string $url): array
    {
        try {
            $response = $request(Http::acceptJson()->timeout(30));
        } catch (ConnectionException $e) {
            throw new AuthException(AuthException::NETWORK, "Could not reach {$url}", previous: $e);
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $text = static fn ($value) => is_string($value) && $value !== '' ? $value : null;

        if (! $response->successful() || is_string($body['error'] ?? null)) {
            // OAuth errors use `error`/`error_description`; REST APIs such as
            // X's return problem details (`title`, `detail`, `reason`).
            $providerError = $text($body['error'] ?? null) ?? $text($body['reason'] ?? null);
            $description = $text($body['error_description'] ?? null)
                ?? $text($body['message'] ?? null)
                ?? $text($body['detail'] ?? null)
                ?? $text($body['title'] ?? null);

            throw new AuthException(
                AuthException::PROVIDER_ERROR,
                $description ?? ($providerError !== null
                    ? "The provider returned \"{$providerError}\""
                    : "Request failed with status {$response->status()}"),
                $providerError,
                $description,
                $response->status(),
            );
        }

        return $body;
    }
}
