<?php

namespace Franciscodlxdev\SocialLogin;

use Illuminate\Contracts\Cache\Repository;

/**
 * Sign-ins whose browser is open, keyed by `state`. PHP serves each native
 * event as a separate request, so the verifier, nonce and provider config
 * wait here between SocialLogin::signIn() and SocialLogin::complete().
 * Stored as JSON (never unserialized) in the app's on-device cache.
 */
final class PendingSignIns
{
    private const PREFIX = 'franciscodlxdev.social-login.pending.';

    private const CURRENT = 'franciscodlxdev.social-login.current';

    public function __construct(
        private readonly Repository $cache,
        private readonly int $ttlSeconds = 600,
    ) {}

    /** @param array<string, mixed> $pending */
    public function put(string $state, array $pending): void
    {
        $this->cache->put(self::PREFIX.$this->key($state), json_encode($pending), $this->ttlSeconds);
        $this->cache->put(self::CURRENT, $state, $this->ttlSeconds);
    }

    /**
     * Removes and returns the pending sign-in for `$state`; a state is only
     * accepted once.
     *
     * @return array<string, mixed>|null
     */
    public function pull(string $state): ?array
    {
        $raw = $this->cache->pull(self::PREFIX.$this->key($state));
        if ($this->cache->get(self::CURRENT) === $state) {
            $this->cache->forget(self::CURRENT);
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    /** State of the most recent sign-in still open, if any. */
    public function current(): ?string
    {
        $state = $this->cache->get(self::CURRENT);

        return is_string($state) ? $state : null;
    }

    public function forgetCurrent(): void
    {
        $state = $this->current();
        if ($state !== null) {
            $this->pull($state);
        }
    }

    private function key(string $state): string
    {
        return hash('sha256', $state);
    }
}
