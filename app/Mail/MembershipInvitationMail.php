<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use App\Models\TenantMembershipLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class MembershipInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public TenantMembershipLevel $level,
        public string $acceptUrl,
        public ?string $invitationMessage = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Invitation to join '.$this->tenant->name.' on Private Gather')
            ->view('emails.membership-invitation');
    }
}
