<?php

namespace Franciscodlxdev\SocialLogin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Native\Mobile\Events\Contracts\DispatchableFromNative;

/**
 * The browser closed without a redirect. `$code` is one of AuthException's
 * codes (CANCELLED when the user closed it). Pass both to SocialLogin::fail().
 */
class AuthSessionFailed implements DispatchableFromNative
{
    use Dispatchable;

    public function __construct(
        public string $code,
        public string $message = '',
    ) {}
}
