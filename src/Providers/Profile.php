<?php

namespace Franciscodlxdev\SocialLogin\Providers;

/** @internal Helpers shared by the profile mappers. */
final class Profile
{
    public static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function id(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    public static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /** Drops null fields. */
    public static function clean(array $profile): array
    {
        return array_filter($profile, static fn ($value) => $value !== null);
    }
}
