<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class CommunicationCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $campaignSubject,
        public string $campaignBody,
        public string $messageType,
        public ?string $unsubscribeUrl = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->campaignSubject)
            ->view('emails.communication-campaign');
    }
}
