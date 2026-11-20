<?php

namespace Franciscodlxdev\SocialLogin;

use Franciscodlxdev\SocialLogin\Native\AuthSessionBridge;
use Franciscodlxdev\SocialLogin\Providers\LinkedIn;
use Franciscodlxdev\SocialLogin\Providers\MercadoLibre;
use Franciscodlxdev\SocialLogin\Providers\Presets;
use Franciscodlxdev\SocialLogin\Providers\Telegram;
use Franciscodlxdev\SocialLogin\Providers\TikTok;
use Franciscodlxdev\SocialLogin\Providers\Twitch;
use Franciscodlxdev\SocialLogin\Providers\X;
use Franciscodlxdev\SocialLogin\Support\IdToken;
use Franciscodlxdev\SocialLogin\Support\Pkce;
use Franciscodlxdev\SocialLogin\Support\TokenClient;
use Franciscodlxdev\SocialLogin\Support\Url;

/**
 * OAuth 2.0 authorization code flow in the system browser, in two steps:
 *
 *   1. signIn() builds the request (state, PKCE, nonce), keeps it pending and
 *      opens the browser. It returns right away.
 *   2. The native side fires AuthSessionCompleted with the redirect URL;
 *      complete() checks it against the pending request and returns the code
 *      (or the tokens and profile with `exchangeCode`). AuthSessionFailed goes
 *      to fail().
 */
class SocialLogin
{
    public function __construct(
        private readonly AuthSessionBridge $bridge,
        private readonly PendingSignIns $pending,
    ) {}

    /**
     * Opens the browser for `$provider`. Options: clientId, redirectUri
     * (required); callbackUri (the app URL the browser lands on when
     * redirectUri is a forwarding page on your backend), scopes, extraParams,
     * prefersEphemeralSession, exchangeCode.
     *
     * @param  array<string, mixed>  $options
     * @return string the `state` of this sign-in
     */
    public function signIn(ProviderConfig $provider, array $options): string
    {
        $clientId = (string) ($options['clientId'] ?? '');
        $redirectUri = (string) ($options['redirectUri'] ?? '');
        $callbackUri = (string) ($options['callbackUri'] ?? $redirectUri);
        $exchangeCode = (bool) ($options['exchangeCode'] ?? false);

        $missing = array_keys(array_filter(['clientId' => $clientId, 'redirectUri' => $redirectUri], static fn ($v) => $v === ''));
        if ($missing !== []) {
            throw new AuthException(AuthException::INVALID_CONFIG, 'Missing required option(s): '.implode(', ', $missing));
        }
        if (! Url::isSecureEndpoint($provider->authorizationEndpoint)) {
            throw new AuthException(AuthException::INVALID_CONFIG, 'authorizationEndpoint must be an https URL');
        }
        if (Url::scheme($redirectUri) === null || Url::scheme($callbackUri) === null) {
            throw new AuthException(AuthException::INVALID_CONFIG, 'redirectUri and callbackUri must be absolute URIs, e.g. myapp://callback');
        }
        if ($exchangeCode && $provider->tokenEndpoint === null) {
            throw new AuthException(AuthException::INVALID_CONFIG, "{$provider->id} cannot exchange the code on-device; do it from your backend");
        }

        $scopes = array_values($options['scopes'] ?? $provider->defaultScopes);
        $state = Pkce::random(16);
        $nonce = $provider->useNonce ? Pkce::random(16) : null;
        $codeVerifier = $provider->usePkce ? Pkce::random(32) : null;

        $url = Url::appendParams($provider->authorizationEndpoint, array_merge(
            $provider->extraAuthParams,
            $options['extraParams'] ?? [],
            [
                'response_type' => 'code',
                $provider->clientIdParam => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => $scopes === [] ? null : implode($provider->scopeSeparator, $scopes),
                'state' => $state,
                'nonce' => $nonce,
                'code_challenge' => $codeVerifier !== null ? Pkce::challenge($codeVerifier) : null,
                'code_challenge_method' => $codeVerifier !== null ? 'S256' : null,
            ],
        ));

        // Only one browser at a time: a new sign-in replaces an abandoned one.
        $this->pending->forgetCurrent();
        $this->pending->put($state, [
            'provider' => $provider->toArray(),
            'clientId' => $clientId,
            'redirectUri' => $redirectUri,
            'callbackUri' => $callbackUri,
            'scopes' => $scopes,
            'nonce' => $nonce,
            'codeVerifier' => $codeVerifier,
            'exchangeCode' => $exchangeCode,
        ]);

        $error = $this->bridge->open($url, $callbackUri, (bool) ($options['prefersEphemeralSession'] ?? false));
        if ($error !== null) {
            $this->pending->pull($state);
            throw AuthException::fromNative($error['code'], $error['message']);
        }

        return $state;
    }

    /**
     * Second step: the URL from AuthSessionCompleted.
     */
    public function complete(string $url): SignInResult
    {
        $params = Url::redirectParams($url);
        $state = $params['state'] ?? null;
        // Only a redirect carrying the state of a sign-in we started counts;
        // the pending entry is consumed so the same redirect cannot be replayed.
        $pending = is_string($state) ? $this->pending->pull($state) : null;
        if ($pending === null) {
            // Some providers omit `state` on errors; report those as such.
            $this->throwIfProviderError($params, []);
            throw new AuthException(AuthException::PROVIDER_ERROR, 'State mismatch: the response does not belong to a sign-in in progress');
        }

        $provider = ProviderConfig::fromArray($pending['provider']);
        if (! Url::matchesRedirectUri($url, $pending['callbackUri'])) {
            throw new AuthException(AuthException::PROVIDER_ERROR, 'The browser returned to an unexpected redirect URI');
        }
        $this->throwIfProviderError($params, $provider->cancelErrors);
        if ($provider->redirectIssuer !== null && isset($params['iss']) && $params['iss'] !== $provider->redirectIssuer) {
            throw new AuthException(AuthException::PROVIDER_ERROR, 'The response came from an unexpected issuer');
        }
        $code = $params['code'] ?? '';
        if ($code === '') {
            throw new AuthException(AuthException::PROVIDER_ERROR, 'The provider did not return an authorization code');
        }

        $result = new SignInResult(
            provider: $provider->id,
            code: $code,
            state: $state,
            redirectUri: $pending['redirectUri'],
            params: $params,
            codeVerifier: $pending['codeVerifier'],
            nonce: $pending['nonce'],
        );

        return $pending['exchangeCode'] ? $this->exchange($provider, $pending, $result) : $result;
    }

    /**
     * Second step when the browser closed without a redirect (the payload of
     * AuthSessionFailed). Clears the pending sign-in and returns the error.
     */
    public function fail(string $code, string $message = ''): AuthException
    {
        $this->pending->forgetCurrent();

        return AuthException::fromNative($code, $message);
    }

    /** Dismisses the browser of the sign-in in progress; AuthSessionFailed (CANCELLED) follows. */
    public function cancel(): void
    {
        $this->bridge->cancel();
    }

    public function isAvailable(): bool
    {
        return $this->bridge->isAvailable();
    }

    /** Revokes a token at the provider, when it has a revocation endpoint. */
    public function signOut(ProviderConfig $provider, string $clientId, string $token, ?string $tokenTypeHint = null): void
    {
        if ($provider->revocationEndpoint !== null) {
            TokenClient::revoke($provider->revocationEndpoint, $clientId, $token, $tokenTypeHint);
        }
    }

    // Provider shortcuts: SocialLogin::signIn(SocialLogin::linkedin(), [...]).

    public function linkedin(array $overrides = []): ProviderConfig
    {
        return LinkedIn::config($overrides);
    }

    public function x(array $overrides = []): ProviderConfig
    {
        return X::config($overrides);
    }

    public function mercadolibre(string $country, array $overrides = []): ProviderConfig
    {
        return MercadoLibre::config($country, $overrides);
    }

    public function twitch(array $overrides = []): ProviderConfig
    {
        return Twitch::config($overrides);
    }

    public function telegram(array $overrides = []): ProviderConfig
    {
        return Telegram::config($overrides);
    }

    public function tiktok(array $overrides = []): ProviderConfig
    {
        return TikTok::config($overrides);
    }

    /** github, discord, slack or strava. */
    public function preset(string $name, array $overrides = []): ProviderConfig
    {
        return Presets::config($name, $overrides);
    }

    /** @param array<string, string> $params */
    private function throwIfProviderError(array $params, array $cancelErrors): void
    {
        $error = $params['error'] ?? null;
        if ($error === null || $error === '') {
            return;
        }
        $cancelled = $error === 'access_denied' || in_array($error, $cancelErrors, true);
        throw new AuthException(
            $cancelled ? AuthException::CANCELLED : AuthException::PROVIDER_ERROR,
            ($params['error_description'] ?? '') !== '' ? $params['error_description'] : "The provider returned \"{$error}\"",
            $error,
            $params['error_description'] ?? null,
        );
    }

    /** @param array<string, mixed> $pending */
    private function exchange(ProviderConfig $provider, array $pending, SignInResult $result): SignInResult
    {
        $tokens = TokenClient::exchangeCode(
            $provider->tokenEndpoint,
            $pending['clientId'],
            $result->code,
            $result->redirectUri,
            $result->codeVerifier,
            $provider->extraTokenParams,
            $provider->clientIdParam,
        );
        $text = static fn ($value) => is_string($value) && $value !== '' ? $value : null;

        $claims = null;
        $idToken = $text($tokens['id_token'] ?? null);
        if ($idToken !== null) {
            $claims = IdToken::decode($idToken);
            IdToken::validate($claims, $provider->issuer, $pending['clientId'], $result->nonce);
        }

        $accessToken = $text($tokens['access_token'] ?? null);
        $userInfoUrl = $provider->userInfoUrlFor($pending['scopes']);
        $profile = match (true) {
            $userInfoUrl !== null && $accessToken !== null => $provider->profile(TokenClient::userInfo($userInfoUrl, $accessToken)),
            $claims !== null => $provider->profile($claims),
            default => null,
        };
        $expiresIn = $tokens['expires_in'] ?? null;

        return new SignInResult(
            provider: $result->provider,
            code: $result->code,
            state: $result->state,
            redirectUri: $result->redirectUri,
            params: $result->params,
            codeVerifier: $result->codeVerifier,
            nonce: $result->nonce,
            accessToken: $accessToken,
            refreshToken: $text($tokens['refresh_token'] ?? null),
            idToken: $idToken,
            expiresIn: is_numeric($expiresIn) ? (int) $expiresIn : null,
            scope: $text($tokens['scope'] ?? null),
            idTokenClaims: $claims,
            profile: $profile,
            tokenResponse: $tokens,
        );
    }
}
