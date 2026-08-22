<?php

namespace App\Services;

use App\Models\CmsNavigationItem;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Models\TenantDomain;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\DomainName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TenantProvisioner
{
    public function create(
        string $name,
        string $type,
        string $subdomain,
        ?User $owner = null,
        array $siteOptions = [],
    ): Tenant {
        $subdomain = Str::slug($subdomain);
        $type = $this->normalizeType($type);
        $this->assertSubdomainAvailable($subdomain);

        return DB::transaction(function () use ($name, $type, $subdomain, $owner, $siteOptions): Tenant {
            $settings = $this->siteSettings($type, $siteOptions);

            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'type' => $type,
                'status' => 'active',
                'plan' => 'starter',
                'settings' => $settings,
            ]);

            $domain = DomainName::platformSubdomain($subdomain, config('platform.root_domain'));
            $tenant->domains()->create([
                'domain' => $domain,
                'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
                'is_primary' => true,
                'status' => TenantDomain::STATUS_ACTIVE,
                'verified_at' => now(),
                'ssl_status' => 'managed',
                'dns_status' => 'active',
                'dns_last_checked_at' => now(),
                'redirect_to_primary' => false,
            ]);

            if ($owner) {
                $tenant->users()->attach($owner->getKey(), [
                    'role' => 'owner',
                    'status' => 'active',
                ]);
            }

            if ($starter = Plan::where('code', 'starter')->first()) {
                TenantSubscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $starter->id,
                    'status' => 'active',
                ]);
            }

            TenantBranding::create([
                'tenant_id' => $tenant->id,
                'primary_color' => '#171319',
                'accent_color' => '#9A3456',
                'font_family' => 'Inter',
                'show_platform_branding' => true,
                'theme' => [
                    'template' => $settings['template'],
                    'surface' => 'dark',
                    'market' => 'adult_lifestyle',
                ],
            ]);

            $this->createDefaultSite($tenant, $settings);

            return $tenant->fresh([
                'domains',
                'pages.sections',
                'branding',
                'subscription.plan',
            ]);
        });
    }

    private function normalizeType(string $type): string
    {
        $type = trim(strtolower($type));

        if ($type === Tenant::TYPE_ORGANIZER) {
            return Tenant::TYPE_ORGANIZATION;
        }

        if (! in_array($type, [
            Tenant::TYPE_CLUB,
            Tenant::TYPE_ORGANIZATION,
            Tenant::TYPE_PRIVATE_HOST,
        ], true)) {
            throw new InvalidArgumentException('Choose a supported website type.');
        }

        return $type;
    }

    private function siteSettings(string $type, array $options): array
    {
        $defaults = [
            'marketplace_enabled' => $type !== Tenant::TYPE_PRIVATE_HOST,
            'site_kind' => $type,
            'market' => 'adult_lifestyle',
            'adult_only' => true,
            'minimum_age' => 18,
            'template' => 'midnight',
            'city' => null,
            'region' => null,
            'tagline' => $type === Tenant::TYPE_CLUB
                ? 'A private adult lifestyle club for connection, community, and unforgettable events.'
                : 'A private adult lifestyle community for couples, singles, groups, and shared experiences.',
            'network_visibility' => $type === Tenant::TYPE_PRIVATE_HOST ? 'private' : 'listed',
            'member_directory_visibility' => 'members',
            'event_attendance_visibility' => 'members',
            'venue_address_visibility' => 'approved_or_ticketed',
            'verification_policy' => 'age_required_identity_optional',
            'membership_profiles' => ['couple', 'individual'],
            'consent_policy_enabled' => true,
            'privacy_first' => true,
        ];

        foreach (['city', 'region', 'tagline', 'template', 'network_visibility', 'marketplace_enabled'] as $key) {
            if (array_key_exists($key, $options)) {
                $defaults[$key] = $options[$key];
            }
        }

        $defaults['marketplace_enabled'] = (bool) $defaults['marketplace_enabled'];

        return $defaults;
    }

    private function assertSubdomainAvailable(string $subdomain): void
    {
        if ($subdomain === '' || in_array($subdomain, config('platform.reserved_subdomains', []), true)) {
            throw new InvalidArgumentException('That Private Gather subdomain is reserved.');
        }

        $domain = DomainName::platformSubdomain($subdomain, config('platform.root_domain'));

        if (TenantDomain::where('domain', $domain)->exists()) {
            throw new InvalidArgumentException('That Private Gather subdomain is already in use.');
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'site';
        $slug = $base;
        $suffix = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function createDefaultSite(Tenant $tenant, array $settings): void
    {
        $home = $tenant->pages()->create([
            'slug' => 'home',
            'title' => 'Home',
            'status' => 'published',
            'is_homepage' => true,
            'published_at' => now(),
        ]);

        $home->sections()->createMany([
            [
                'type' => 'hero',
                'name' => 'Homepage Hero',
                'sort_order' => 10,
                'is_enabled' => true,
                'content' => [
                    'eyebrow' => $tenant->isClub() ? 'PRIVATE ADULT LIFESTYLE CLUB' : 'PRIVATE ADULT LIFESTYLE COMMUNITY',
                    'heading' => $tenant->name,
                    'body' => $settings['tagline'],
                    'primary_label' => 'Explore Events',
                    'primary_url' => '/events',
                ],
                'settings' => ['alignment' => 'left'],
            ],
            [
                'type' => 'event_grid',
                'name' => 'Upcoming Lifestyle Events',
                'sort_order' => 20,
                'is_enabled' => true,
                'content' => [
                    'heading' => 'Upcoming Events',
                    'limit' => 6,
                ],
                'settings' => [],
            ],
            [
                'type' => 'image_text',
                'name' => $tenant->isClub() ? 'The Club Experience' : 'Our Lifestyle Community',
                'sort_order' => 30,
                'is_enabled' => true,
                'content' => [
                    'heading' => $tenant->isClub() ? 'A better night starts with trust' : 'Community built around trust',
                    'body' => $tenant->isClub()
                        ? 'Introduce your venue, couples and singles policies, membership experience, amenities, dress expectations, privacy standards, and what first-time guests should expect.'
                        : 'Explain your adult lifestyle community, membership expectations, chapters or local groups, event culture, privacy standards, and how new members become part of the community.',
                ],
                'settings' => [],
            ],
            [
                'type' => 'cta',
                'name' => 'Member CTA',
                'sort_order' => 40,
                'is_enabled' => true,
                'content' => [
                    'heading' => $tenant->isClub() ? 'Join the club community' : 'Join the lifestyle community',
                    'body' => 'Create an adult Private Gather profile to request membership, RSVP, purchase eligible tickets, and manage your event experience privately.',
                    'button_label' => 'Create Member Profile',
                    'button_url' => '/register',
                ],
                'settings' => [],
            ],
        ]);

        $this->createContentPage(
            $tenant,
            'about',
            'About',
            'About '.$tenant->name,
            $tenant->isClub()
                ? 'Tell prospective members about your adult lifestyle club, venue, atmosphere, membership philosophy, amenities, and the experience you provide for consenting adults.'
                : 'Tell prospective members about your adult lifestyle organization, mission, community, leadership, chapters, and the experience you provide for consenting adults.',
        );

        $this->createContentPage(
            $tenant,
            'membership',
            'Membership',
            'Membership',
            'Explain eligibility for couples and individuals, application and verification requirements, membership levels, benefits, guest policies, renewal terms, and how new members are approved.',
        );

        $this->createContentPage(
            $tenant,
            'first-visit',
            'First Visit',
            'Your First Visit',
            $tenant->isClub()
                ? 'Help first-time guests understand arrival and check-in, ID requirements, dress expectations, privacy, phones and photography rules, guest etiquette, consent standards, and when a private venue address becomes visible.'
                : 'Help new members understand verification, introductions, event etiquette, privacy, community expectations, consent standards, and how to participate comfortably for the first time.',
        );

        $this->createContentPage(
            $tenant,
            'rules',
            $tenant->isClub() ? 'House Rules & Consent' : 'Community Rules & Consent',
            $tenant->isClub() ? 'House Rules & Consent' : 'Community Rules & Consent',
            'Publish clear standards covering affirmative consent, boundaries, respectful conduct, privacy, photography, phones, intoxication, prohibited behavior, dress requirements, reporting concerns, and removal or suspension policies.',
        );

        $this->createContentPage(
            $tenant,
            'privacy',
            'Privacy & Discretion',
            'Privacy & Discretion',
            'Explain how member information, event attendance, photos, private venue details, directories, and communications are protected and which visibility choices members control.',
        );

        $this->createContentPage(
            $tenant,
            'contact',
            'Contact',
            'Contact '.$tenant->name,
            'Add a discreet contact method, member support information, operating hours, event questions, and the best way to reach your team without publishing private member or venue information unnecessarily.',
        );

        $headerItems = [
            ['Events', '/events'],
            ['About', '/about'],
            ['Membership', '/page/membership'],
            ['First Visit', '/page/first-visit'],
            [$tenant->isClub() ? 'Rules & Consent' : 'Community Standards', '/page/rules'],
            ['Privacy', '/page/privacy'],
            ['Contact', '/page/contact'],
        ];

        foreach ($headerItems as $index => [$label, $url]) {
            CmsNavigationItem::create([
                'tenant_id' => $tenant->id,
                'location' => 'header',
                'label' => $label,
                'url' => $url,
                'sort_order' => ($index + 1) * 10,
                'is_enabled' => true,
            ]);

            CmsNavigationItem::create([
                'tenant_id' => $tenant->id,
                'location' => 'footer',
                'label' => $label,
                'url' => $url,
                'sort_order' => ($index + 1) * 10,
                'is_enabled' => true,
            ]);
        }

        $publicSettings = [
            'tagline' => $settings['tagline'],
            'footer_text' => 'Private adult lifestyle events, memberships, and member access powered securely by Private Gather.',
            'city' => (string) ($settings['city'] ?? ''),
            'region' => (string) ($settings['region'] ?? ''),
        ];

        foreach ($publicSettings as $key => $value) {
            SiteSetting::create([
                'tenant_id' => $tenant->id,
                'group' => 'general',
                'key' => $key,
                'value' => $value,
                'type' => 'string',
                'is_public' => true,
            ]);
        }
    }

    private function createContentPage(
        Tenant $tenant,
        string $slug,
        string $title,
        string $heading,
        string $body,
    ): void {
        $page = $tenant->pages()->create([
            'slug' => $slug,
            'title' => $title,
            'status' => 'published',
            'is_homepage' => false,
            'published_at' => now(),
        ]);

        $page->sections()->create([
            'type' => 'rich_text',
            'name' => $title,
            'sort_order' => 10,
            'is_enabled' => true,
            'content' => [
                'heading' => $heading,
                'body' => $body,
            ],
            'settings' => [],
        ]);
    }
}
