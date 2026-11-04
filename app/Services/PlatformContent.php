<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Support\ThemeCatalog;

final class PlatformContent
{
    public const OFFICIAL_LOGO = '/assets/branding/private-gather-logo.png';
    public const DEFAULT_HERO = '/assets/showcase/photos/hero-neon-lounge.webp';
    public const DEFAULT_ABOUT = '/assets/showcase/photos/event-private-social.webp';

    private const LEGACY_PLACEHOLDER_MEDIA = [
        '/assets/showcase/platform-hero.svg' => self::DEFAULT_HERO,
        '/assets/showcase/community.svg' => self::DEFAULT_ABOUT,
    ];

    public const DEFAULTS = [
        'brand_name' => 'Private Gather',
        'logo_url' => self::OFFICIAL_LOGO,
        'theme_preset' => ThemeCatalog::PLATFORM_DEFAULT,
        'header_cta_label' => 'Join Private Gather',
        'header_cta_url' => '/register',
        'hero_eyebrow' => 'THE PRIVATE SOCIAL NETWORK',
        'hero_heading' => 'For Lifestyle Communities',
        'hero_body' => 'Private Gather connects like-minded adults with exclusive events, trusted clubs and private groups in a discreet, respectful and secure environment.',
        'hero_image_url' => self::DEFAULT_HERO,
        'hero_primary_label' => 'Explore Events',
        'hero_primary_url' => '/events',
        'hero_secondary_label' => 'Browse Clubs',
        'hero_secondary_url' => '/clubs',
        'featured_eyebrow' => 'UPCOMING',
        'featured_heading' => 'Featured gatherings',
        'organizations_eyebrow' => 'PRIVATE GATHER COMMUNITY',
        'organizations_heading' => 'Clubs & organizers',
        'domain_eyebrow' => 'YOUR BRAND, POWERED BY PRIVATE GATHER',
        'domain_heading' => 'Use a Private Gather subdomain or your own domain.',
        'domain_body' => 'Launch instantly on a hosted Private Gather subdomain, then connect your own domain while your website, events and member tools continue running on Private Gather infrastructure.',
        'footer_text' => 'Exclusive connections. Private by nature. Discover events, clubs, private hosts and social experiences through Private Gather.',
        'about_heading' => 'About Private Gather',
        'about_body' => 'Private Gather is a multi-tenant adult social-event, RSVP, ticketing, membership and hosted website platform for clubs, organizers, venues and private hosts.',
        'about_image_url' => self::DEFAULT_ABOUT,
        'show_featured_events' => '1',
        'show_organizations' => '1',
        'show_domain_section' => '1',
    ];

    public function all(): array
    {
        $stored = PlatformSetting::pluck('value', 'key')->all();
        $values = array_replace(self::DEFAULTS, $stored);
        if (! array_key_exists('brand_name', $stored)) {
            $values['brand_name'] = (string) config('app.name', self::DEFAULTS['brand_name']);
        }

        // 1.3.6 wrote generic SVG showcase art into fresh databases. Treat only those
        // exact known placeholder paths as defaults; preserve any administrator-chosen media.
        foreach (['hero_image_url', 'about_image_url'] as $key) {
            $current = (string) ($values[$key] ?? '');
            if (isset(self::LEGACY_PLACEHOLDER_MEDIA[$current])) {
                $values[$key] = self::LEGACY_PLACEHOLDER_MEDIA[$current];
            }
        }

        // The central Private Gather identity is fixed to the official crest.
        // Tenant organizations remain free to upload/use their own brand assets.
        $values['logo_url'] = self::OFFICIAL_LOGO;
        $values['theme_preset'] = ThemeCatalog::platform((string) ($values['theme_preset'] ?? ''))['key'];

        return $values;
    }
}