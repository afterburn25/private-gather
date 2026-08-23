<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\PlatformNotification;

final class CommunityNotifier
{
    /** @param array<string,mixed> $data */
    public function notify(int $userId, ?int $tenantId, string $type, array $data): ?PlatformNotification
    {
        if ($userId < 1) {
            return null;
        }

        $preferenceColumn = match ($type) {
            'message' => 'in_app_messages',
            'reaction' => 'in_app_reactions',
            'connection' => 'in_app_connections',
            'event' => 'in_app_events',
            default => null,
        };

        if ($preferenceColumn !== null) {
            $preferences = NotificationPreference::query()->where('user_id', $userId)->first();
            if ($preferences && ! (bool) $preferences->{$preferenceColumn}) {
                return null;
            }
        }

        return PlatformNotification::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId && $tenantId > 0 ? $tenantId : null,
            'type' => mb_substr($type, 0, 100),
            'data' => $data,
        ]);
    }
}
