<?php

namespace Franciscodlxdev\SocialLogin;

use RuntimeException;
use Throwable;

/**
 * The single error type of the family: the same codes as the React Native,
 * Flutter, Capacitor and NativeScript plugins.
 */
class AuthException extends RuntimeException
{
    public const CANCELLED = 'CANCELLED';

    public const NETWORK = 'NETWORK';

    public const INVALID_CONFIG = 'INVALID_CONFIG';

    public const PROVIDER_ERROR = 'PROVIDER_ERROR';

    public const NOT_SUPPORTED = 'NOT_SUPPORTED';

    public const CODES = [
        self::CANCELLED,
        self::NETWORK,
        self::INVALID_CONFIG,
        self::PROVIDER_ERROR,
        self::NOT_SUPPORTED,
    ];

    public function __construct(
        public readonly string $authCode,
        string $message,
        public readonly ?string $providerError = null,
        public readonly ?string $providerErrorDescription = null,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** Builds one from a code the native side reported, falling back to PROVIDER_ERROR. */
    public static function fromNative(string $code, string $message): self
    {
        return new self(
            in_array($code, self::CODES, true) ? $code : self::PROVIDER_ERROR,
            $message !== '' ? $message : 'Authentication failed',
        );
    }
}
