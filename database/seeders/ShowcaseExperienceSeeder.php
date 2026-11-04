<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ShowcaseExperienceSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->where('settings->showcase_content', true)->orderBy('id')->get()->each(function (Tenant $tenant): void {
            $tenant->events()->orderBy('starts_at')->get()->each(function (Event $event, int $index) use ($tenant): void {
                $city = $event->city ?: (string) data_get($tenant->settings, 'city', 'the city');
                $event->update([
                    'featured_at' => $index === 0 ? now()->subMinutes($tenant->id % 30) : null,
                    'hosts' => [
                        ['name' => $tenant->name.' Host Team', 'role' => 'Community hosts'],
                        ['name' => 'Guest Experience', 'role' => 'Arrival & hospitality'],
                    ],
                    'schedule' => [
                        ['time' => $event->starts_at->format('g:i A'), 'label' => 'Arrival & welcome'],
                        ['time' => $event->starts_at->copy()->addHour()->format('g:i A'), 'label' => 'Hosted introductions & social'],
                        ['time' => $event->starts_at->copy()->addHours(2)->format('g:i A'), 'label' => 'Main event experience'],
                    ],
                    'faq' => [
                        ['question' => 'When is the exact location shared?', 'answer' => 'Private location details follow the event privacy setting and are shown only when the configured access condition is met.'],
                        ['question' => 'What should I bring?', 'answer' => 'Bring the admission confirmation shown in Private Gather and follow the published dress code and house rules.'],
                        ['question' => 'Can I update my RSVP?', 'answer' => 'Yes. Use your Private Gather event access before registration closes or contact the host team.'],
                    ],
                    'updates' => [
                        ['title' => 'Event page ready', 'body' => 'Review the schedule, admission options and house expectations before arriving in '.$city.'.'],
                    ],
                    // Showcase events intentionally do not contain precise venue coordinates.
                    'latitude' => null,
                    'longitude' => null,
                ]);
            });
        });
    }
}
