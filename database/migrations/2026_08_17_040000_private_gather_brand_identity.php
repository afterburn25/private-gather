<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $replacements = [
            'brand_name' => ['Private Gather', 'Private Gather'],
            'hero_eyebrow' => ['ADULT SOCIAL EVENTS • 18+ ONLY', 'PRIVATE EVENTS • CLUBS • SOCIAL EXPERIENCES • 18+ ONLY'],
            'hero_heading' => ['Discover your next social experience.', 'Gather privately. Connect confidently.'],
            'hero_body' => ['Find public events, follow organizers, manage RSVPs and tickets, or launch a complete hosted website for your club or event brand.', 'Discover private social events, clubs and trusted organizers, manage RSVPs and tickets, or launch a complete hosted website for your own event community.'],
            'hero_secondary_label' => ['Create Organizer Site', 'Host on Private Gather'],
            'featured_heading' => ['Featured events', 'Featured gatherings'],
            'organizations_eyebrow' => ['COMMUNITY', 'PRIVATE GATHER COMMUNITY'],
            'domain_eyebrow' => ['YOUR WEBSITE, OUR INFRASTRUCTURE', 'YOUR BRAND, POWERED BY PRIVATE GATHER'],
            'domain_heading' => ['Use a hosted subdomain or your own domain.', 'Use a Private Gather subdomain or your own domain.'],
            'domain_body' => ['Start with a hosted platform subdomain, then connect your own domain while the website remains hosted on the platform.', 'Launch instantly on a hosted Private Gather subdomain, then connect your own domain while your website, events and member tools continue running on Private Gather infrastructure.'],
            'footer_text' => ['Adults-only social event discovery, RSVP, ticketing and organizer website platform.', 'Exclusive connections. Private by nature. Discover events, clubs, private hosts and social experiences through Private Gather.'],
            'about_heading' => ['About the platform', 'About Private Gather'],
            'about_body' => ['A multi-tenant adult social-event, RSVP, ticketing and organizer website platform.', 'Private Gather is a multi-tenant adult social-event, RSVP, ticketing, membership and hosted website platform for clubs, organizers, venues and private hosts.'],
        ];

        foreach ($replacements as $key => [$old, $new]) {
            DB::table('platform_settings')->where('key', $key)->where('value', $old)->update([
                'value' => $new,
                'updated_at' => now(),
            ]);
        }

        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'logo_url'],
            ['group' => 'content', 'value' => '/assets/branding/private-gather-logo.png', 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        // Brand identity changes are intentionally not reverted automatically.
    }
};
