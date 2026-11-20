<?php

namespace Franciscodlxdev\SocialLogin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Native\Mobile\Events\Contracts\DispatchableFromNative;

/**
 * The browser came back to the app. Pass `$url` to SocialLogin::complete():
 * it only accepts a redirect that carries the `state` of a sign-in this app
 * started, so a forged event cannot inject a code.
 */
class AuthSessionCompleted implements DispatchableFromNative
{
    use Dispatchable;

    public function __construct(
        public string $url,
    ) {}
}
