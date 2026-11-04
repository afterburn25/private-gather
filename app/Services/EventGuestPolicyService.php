<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Validation\ValidationException;

final class EventGuestPolicyService
{
    public const NONE = 'no_guests';
    public const ALLOWED = 'allowed';
    public const APPROVAL = 'approval_required';

    public function assertPartySize(Event $event, int $partySize): void
    {
        $limit = max(1, min(10, (int) ($event->max_party_size ?: 10)));
        if ($partySize < 1 || $partySize > $limit) {
            throw ValidationException::withMessages([
                'guest_count' => 'Party size must be between 1 and '.$limit.' for this event.',
            ]);
        }
        if ((string) $event->guest_policy === self::NONE && $partySize > 1) {
            throw ValidationException::withMessages([
                'guest_count' => 'This event does not allow additional guests.',
            ]);
        }
    }

    public function requiresApproval(Event $event, int $partySize): bool
    {
        return (string) $event->guest_policy === self::APPROVAL && $partySize > 1;
    }

    public function label(Event $event): string
    {
        return match ((string) $event->guest_policy) {
            self::NONE => 'No additional guests',
            self::APPROVAL => 'Additional guests require approval',
            default => 'Guests allowed',
        };
    }
}
