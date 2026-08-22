<?php

declare(strict_types=1);

namespace App\Support;

final class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled', false);
    }

    public static function host(): string
    {
        return strtolower(trim((string) config('demo.host', 'demo.privategather.com')));
    }

    public static function allows(string $channel): bool
    {
        if (! self::enabled()) {
            return true;
        }

        return (bool) config('demo.outbound.'.$channel, false);
    }

    public static function resetEnabled(): bool
    {
        return self::enabled() && (bool) config('demo.reset.enabled', false);
    }
}
