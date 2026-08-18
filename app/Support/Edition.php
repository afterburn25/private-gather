<?php

declare(strict_types=1);

namespace App\Support;

final class Edition
{
    public const HOSTED = 'hosted';
    public const SELF_HOSTED = 'self_hosted';

    public static function current(): string
    {
        $value = strtolower(trim((string) config('edition.name', self::HOSTED)));

        return in_array($value, [self::HOSTED, self::SELF_HOSTED], true)
            ? $value
            : self::HOSTED;
    }

    public static function isHosted(): bool
    {
        return self::current() === self::HOSTED;
    }

    public static function isSelfHosted(): bool
    {
        return self::current() === self::SELF_HOSTED;
    }

    public static function selfHostedTenantId(): ?int
    {
        $id = (int) config('edition.self_hosted.tenant_id', 0);

        return $id > 0 ? $id : null;
    }

    public static function selfHostedVisibility(): string
    {
        $value = strtolower(trim((string) config('edition.self_hosted.visibility', 'private')));

        return in_array($value, ['private', 'public'], true) ? $value : 'private';
    }

    public static function selfHostedRegistration(): string
    {
        $value = strtolower(trim((string) config('edition.self_hosted.registration', 'approval')));

        return in_array($value, ['open', 'approval', 'disabled'], true) ? $value : 'approval';
    }

    public static function registrationEnabled(): bool
    {
        return self::isHosted() || self::selfHostedRegistration() !== 'disabled';
    }
}
