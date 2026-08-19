<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WaitlistPromotedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $eventTitle,
        public readonly string $eventUrl,
        public readonly int $guestCount,
        public readonly ?string $startsAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject('You are off the waitlist: '.$this->eventTitle)
            ->greeting('Good news!')
            ->line('An organizer has promoted your waitlisted party to an approved RSVP for '.$this->eventTitle.'.')
            ->line('Approved party size: '.$this->guestCount.'.');

        if ($this->startsAt) {
            $message->line('Event starts: '.$this->startsAt.'.');
        }

        return $message
            ->action('View Event', $this->eventUrl)
            ->line('If your plans have changed, please cancel your RSVP so the organizer can offer the space to someone else.');
    }
}
