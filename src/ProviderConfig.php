<?php

namespace Franciscodlxdev\SocialLogin;

/**
 * What a provider contributes on top of the core: endpoints, scopes and how
 * to read its profile. The flow and the security checks live in the core.
 *
 * Callables are stored as `Class::method` strings so a pending sign-in can be
 * kept as plain JSON while the browser is open.
 */
final class ProviderConfig
{
    /**
     * @param  list<string>  $defaultScopes
     * @param  list<string>  $cancelErrors  provider `error` values that mean the user backed out (besides `access_denied`)
     * @param  array<string, string>  $extraAuthParams
     * @param  array<string, string>  $extraTokenParams
     * @param  string|null  $mapProfile  `Class::method` receiving the userinfo response or id_token claims
     * @param  string|null  $userInfoUrl  `Class::method(string $endpoint, array $scopes): string`
     */
    public function __construct(
        public readonly string $id,
        public readonly string $authorizationEndpoint,
        public readonly array $defaultScopes = [],
        public readonly ?string $tokenEndpoint = null,
        public readonly ?string $userInfoEndpoint = null,
        public readonly ?string $revocationEndpoint = null,
        public readonly ?string $issuer = null,
        public readonly string $scopeSeparator = ' ',
        public readonly bool $usePkce = true,
        public readonly bool $useNonce = false,
        public readonly string $clientIdParam = 'client_id',
        public readonly array $cancelErrors = [],
        public readonly array $extraAuthParams = [],
        public readonly array $extraTokenParams = [],
        public readonly ?string $mapProfile = null,
        public readonly ?string $userInfoUrl = null,
        /** RFC 9207: a response carrying another `iss` is rejected. */
        public readonly ?string $redirectIssuer = null,
    ) {}

    /** Copy with some fields replaced, e.g. a test server's endpoints. */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $known = array_keys(get_class_vars(self::class));

        return new self(...array_intersect_key($data, array_flip($known)));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function profile(array $raw): array
    {
        return $this->mapProfile !== null && is_callable($this->mapProfile)
            ? ($this->mapProfile)($raw)
            : $raw;
    }

    /** @param list<string> $scopes */
    public function userInfoUrlFor(array $scopes): ?string
    {
        if ($this->userInfoEndpoint === null) {
            return null;
        }

        return $this->userInfoUrl !== null && is_callable($this->userInfoUrl)
            ? ($this->userInfoUrl)($this->userInfoEndpoint, $scopes)
            : $this->userInfoEndpoint;
    }
}
