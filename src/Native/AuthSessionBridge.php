<?php

namespace Franciscodlxdev\SocialLogin\Native;

/**
 * The native side: opens the system browser (ASWebAuthenticationSession on
 * iOS, Custom Tabs on Android). It answers through events:
 * AuthSessionCompleted with the redirect URL, or AuthSessionFailed.
 */
interface AuthSessionBridge
{
    /**
     * Starts the browser and returns right away; null when it opened, or
     * `['code' => …, 'message' => …]` when it could not.
     *
     * @return array{code: string, message: string}|null
     */
    public function open(string $url, string $callbackUri, bool $ephemeral): ?array;

    public function cancel(): void;

    public function isAvailable(): bool;
}
