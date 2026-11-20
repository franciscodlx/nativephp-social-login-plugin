<?php

namespace Franciscodlxdev\SocialLogin\Native;

final class NativePhpBridge implements AuthSessionBridge
{
    public function open(string $url, string $callbackUri, bool $ephemeral): ?array
    {
        $response = $this->call('SocialLogin.OpenAuthSession', [
            'url' => $url,
            'callbackUri' => $callbackUri,
            'prefersEphemeralSession' => $ephemeral,
        ]);
        if ($response === null) {
            return ['code' => 'NOT_SUPPORTED', 'message' => 'The system browser sign-in only runs inside a NativePHP app'];
        }
        if (($response['status'] ?? null) === 'error') {
            // Our native functions put the family's error code in data.code.
            $code = $response['data']['code'] ?? $response['code'] ?? 'PROVIDER_ERROR';

            return ['code' => (string) $code, 'message' => (string) ($response['message'] ?? 'The sign-in could not be started')];
        }

        return null;
    }

    public function cancel(): void
    {
        $this->call('SocialLogin.CancelAuthSession', []);
    }

    public function isAvailable(): bool
    {
        return function_exists('nativephp_call');
    }

    /** @return array<string, mixed>|null */
    private function call(string $method, array $params): ?array
    {
        if (! function_exists('nativephp_call')) {
            return null;
        }
        $result = nativephp_call($method, json_encode($params));
        $decoded = is_string($result) ? json_decode($result, true) : null;
        if (! is_array($decoded)) {
            return [];
        }

        // Successful calls come back wrapped as { data: {...} } on some versions.
        return isset($decoded['data']) && is_array($decoded['data']) && ! isset($decoded['status'])
            ? $decoded['data']
            : $decoded;
    }
}
