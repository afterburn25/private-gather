<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TenantMembershipTerm;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class MembershipRenewalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly TenantMembershipTerm $term) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->term->loadMissing(['tenant', 'level']);
        $boundary = $this->term->current_period_ends_at;
        $verb = $this->term->cancel_at_period_end ? 'ends' : ($this->term->auto_renew ? 'is due to renew' : 'reaches its renewal date');

        return [
            'label' => $this->term->tenant->name.' membership '.$verb.($boundary ? ' '.$boundary->format('M j, Y') : ''),
            'tenant_id' => $this->term->tenant_id,
            'tenant_name' => $this->term->tenant->name,
            'membership_level' => $this->term->level->name,
            'current_period_ends_at' => $boundary?->toIso8601String(),
            'expires_at' => $this->term->expires_at?->toIso8601String(),
            'cancel_at_period_end' => $this->term->cancel_at_period_end,
            'auto_renew' => $this->term->auto_renew,
            'category' => 'membership',
        ];
    }
}
