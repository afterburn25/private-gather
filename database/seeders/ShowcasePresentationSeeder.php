<?php

namespace Database\Seeders;

use App\Models\CmsSection;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ShowcasePresentationSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()
            ->where('settings->showcase_content', true)
            ->orderBy('id')
            ->get()
            ->each(function (Tenant $tenant): void {
                $settings = is_array($tenant->settings) ? $tenant->settings : [];
                $settings['cover_image_path'] = '/assets/showcase/club-night.svg';
                $tenant->forceFill(['settings' => $settings])->save();

                CmsSection::query()
                    ->whereHas('page', fn ($q) => $q->where('tenant_id', $tenant->id))
                    ->whereIn('name', ['showcase-hero', 'showcase-story'])
                    ->get()
                    ->each(function (CmsSection $section) use ($tenant): void {
                        $content = is_array($section->content) ? $section->content : [];
                        if ($section->name === 'showcase-hero') {
                            $content['image_url'] = '/assets/showcase/club-night.svg';
                            $content['image_alt'] = $tenant->name.' fictional showcase atmosphere';
                        } else {
                            $content['image_url'] = '/assets/showcase/community.svg';
                            $content['image_alt'] = 'Private Gather fictional community showcase artwork';
                        }
                        $section->update(['content' => $content]);
                    });

                $tenant->events()->orderBy('starts_at')->get()->each(function ($event, int $index): void {
                    $cover = $index % 2 === 0
                        ? '/assets/showcase/event-night.svg'
                        : '/assets/showcase/event-social.svg';
                    $event->update([
                        'cover_image_path' => $cover,
                        'gallery' => [
                            $cover,
                            '/assets/showcase/community.svg',
                            '/assets/showcase/club-night.svg',
                        ],
                    ]);
                });
            });
    }
}
