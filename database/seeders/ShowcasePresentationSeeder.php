<?php

namespace Database\Seeders;

use App\Models\CmsSection;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ShowcasePresentationSeeder extends Seeder
{
    private const HERO = '/assets/showcase/photos/hero-neon-lounge.webp';
    private const CLUB = '/assets/showcase/photos/club-city-night.webp';
    private const SOCIAL = '/assets/showcase/photos/event-private-social.webp';

    public function run(): void
    {
        Tenant::query()
            ->where('settings->showcase_content', true)
            ->orderBy('id')
            ->get()
            ->each(function (Tenant $tenant): void {
                $settings = is_array($tenant->settings) ? $tenant->settings : [];
                $settings['cover_image_path'] = self::CLUB;
                $tenant->forceFill(['settings' => $settings])->save();

                CmsSection::query()
                    ->whereHas('page', fn ($q) => $q->where('tenant_id', $tenant->id))
                    ->whereIn('name', ['showcase-hero', 'showcase-story'])
                    ->get()
                    ->each(function (CmsSection $section) use ($tenant): void {
                        $content = is_array($section->content) ? $section->content : [];
                        if ($section->name === 'showcase-hero') {
                            $content['image_url'] = self::CLUB;
                            $content['image_alt'] = $tenant->name.' fictional showcase nightlife atmosphere';
                        } else {
                            $content['image_url'] = self::SOCIAL;
                            $content['image_alt'] = 'Private Gather fictional community showcase atmosphere';
                        }
                        $section->update(['content' => $content]);
                    });

                $covers = [self::CLUB, self::SOCIAL, self::HERO];
                $tenant->events()->orderBy('starts_at')->get()->each(function ($event, int $index) use ($covers): void {
                    $cover = $covers[$index % count($covers)];
                    $event->update([
                        'cover_image_path' => $cover,
                        'gallery' => [
                            $cover,
                            $covers[($index + 1) % count($covers)],
                            $covers[($index + 2) % count($covers)],
                        ],
                    ]);
                });
            });
    }
}