<?php

namespace Franciscodlxdev\SocialLogin\Tests;

use Franciscodlxdev\SocialLogin\Native\AuthSessionBridge;
use Franciscodlxdev\SocialLogin\Support\Url;

/** Stands in for the native side and plays the provider's redirect. */
final class FakeBridge implements AuthSessionBridge
{
    /** @var list<array{url: string, callbackUri: string, ephemeral: bool}> */
    public array $opened = [];

    public ?array $openError = null;

    public bool $cancelled = false;

    public function open(string $url, string $callbackUri, bool $ephemeral): ?array
    {
        $this->opened[] = compact('url', 'callbackUri', 'ephemeral');

        return $this->openError;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function sentParams(int $index = -1): array
    {
        $url = $this->opened[$index < 0 ? count($this->opened) + $index : $index]['url'];

        return Url::decodeParams(substr($url, strpos($url, '?') + 1));
    }

    /** The redirect a provider that approves would send back. */
    public function approve(array $params = ['code' => 'the-code']): string
    {
        $last = end($this->opened);

        return Url::appendParams($last['callbackUri'], array_merge($params, ['state' => $this->sentParams()['state']]));
    }
}
