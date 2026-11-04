<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShowcaseContentSeeder extends Seeder
{
    public function run(): void
    {
        $rootDomain = (string) config('platform.root_domain', 'privategather.com');

        $clubs = [
            ['name'=>'Velvet Room Social Club','slug'=>'velvet-room','city'=>'Las Vegas','region'=>'NV','timezone'=>'America/Los_Angeles','theme'=>'velvet-lounge','summary'=>'An upscale private social club built around polished nightlife, curated gatherings and member hospitality.','events'=>[['Masquerade at Velvet Room','Formal','An elegant masked evening with cocktails, music and a polished late-night atmosphere.'],['Velvet Sundays','Social','A relaxed members-and-guests social designed for conversation, cocktails and new connections.']]],
            ['name'=>'Eclipse House','slug'=>'eclipse-house','city'=>'Dallas','region'=>'TX','timezone'=>'America/Chicago','theme'=>'art-deco-house','summary'=>'A private metropolitan club pairing art-deco glamour with intimate member events and themed evenings.','events'=>[['Midnight at Eclipse','Party','A late-night signature event with elevated dress, DJs and multiple social spaces.'],['Eclipse Supper Social','Dining','A seated social dinner followed by music and after-hours conversation.']]],
            ['name'=>'Desire Lounge','slug'=>'desire-lounge','city'=>'Scottsdale','region'=>'AZ','timezone'=>'America/Phoenix','theme'=>'neon-underground','summary'=>'A nightlife-forward private lounge with energetic socials, guest DJs and members-only late nights.','events'=>[['Electric Desire','Party','High-energy club night with immersive lighting, lounge rooms and a curated guest list.'],['After Hours Social','Social','A smaller late-night social for members who prefer conversation and a more intimate pace.']]],
            ['name'=>'Oasis Private Club','slug'=>'oasis-private-club','city'=>'Palm Springs','region'=>'CA','timezone'=>'America/Los_Angeles','theme'=>'resort-night','summary'=>'A destination-style community for resort weekends, pool gatherings and relaxed desert social experiences.','events'=>[['Sunset Pool Soirée','Pool','An evening pool gathering that transitions from sunset cocktails into a private night social.'],['Desert Weekend Escape','Travel','A destination weekend combining resort time, hosted dinners and private evening events.']]],
            ['name'=>'Noir Society','slug'=>'noir-society','city'=>'New York','region'=>'NY','timezone'=>'America/New_York','theme'=>'gallery-minimal','summary'=>'An editorial, city-centered private society focused on culture, style and carefully hosted social experiences.','events'=>[['Noir Gallery Night','Culture','A private gallery-style social with cocktails, conversation and a late-night lounge set.'],['Black & White Affair','Formal','A monochrome formal evening with a curated guest list and classic New York after-dark energy.']]],
            ['name'=>'The Garden Society','slug'=>'garden-society','city'=>'Atlanta','region'=>'GA','timezone'=>'America/New_York','theme'=>'garden-estate','summary'=>'A private social community inspired by estate gatherings, garden evenings and warm Southern hospitality.','events'=>[['Moonlit Garden Party','Social','An outdoor evening social with garden lighting, cocktails and intimate conversation areas.'],['Estate Brunch Social','Brunch','A relaxed daytime gathering with brunch, music and a softer social pace.']]],
            ['name'=>'The Ember Room','slug'=>'ember-room','city'=>'Chicago','region'=>'IL','timezone'=>'America/Chicago','theme'=>'industrial-loft','summary'=>'A modern loft-style private club with bold event production, urban socials and late-night programming.','events'=>[['Ember Loft Night','Party','A warehouse-inspired club social with DJs, cocktail stations and multiple member lounges.'],['Thursday Social Club','Social','An easygoing weeknight meetup for members and approved guests.']]],
            ['name'=>'Maison Rouge','slug'=>'maison-rouge','city'=>'New Orleans','region'=>'LA','timezone'=>'America/Chicago','theme'=>'cabaret-rouge','summary'=>'A theatrical private community blending cabaret atmosphere, hospitality and distinctive themed events.','events'=>[['Rouge After Dark','Cabaret','A theatrical evening with live entertainment, cocktails and a dramatic late-night setting.'],['Champagne Social','Social','A polished cocktail social with live music and a guest-friendly early-evening format.']]],
            ['name'=>'Villa Privé','slug'=>'villa-prive','city'=>'Miami','region'=>'FL','timezone'=>'America/New_York','theme'=>'private-villa','summary'=>'A warm, hospitality-led private club for villa gatherings, dinner socials and upscale weekend experiences.','events'=>[['Villa Nights','Social','A private villa evening with cocktails, conversation and curated music.'],['White Dinner','Dining','An elegant dinner social with a white-attire theme and an intimate after-dinner gathering.']]],
            ['name'=>'Aurora Social Club','slug'=>'aurora-social','city'=>'Denver','region'=>'CO','timezone'=>'America/Denver','theme'=>'social-club','summary'=>'A modern member community for events, friendships, group activities and approachable social discovery.','events'=>[['Aurora Rooftop Social','Social','A modern rooftop meetup with skyline views, music and relaxed member introductions.'],['Mountain Weekend Mixer','Travel','A weekend social built around a mountain stay, hosted dinner and community activities.']]],
        ];

        foreach ($clubs as $index => $clubData) {
            $tenant = Tenant::updateOrCreate(
                ['slug' => $clubData['slug']],
                [
                    'name' => $clubData['name'],
                    'type' => Tenant::TYPE_CLUB,
                    'status' => 'active',
                    'plan' => 'starter',
                    'settings' => [
                        'marketplace_enabled' => true,
                        'market' => 'adult_lifestyle',
                        'adult_only' => true,
                        'showcase_content' => true,
                        'city' => $clubData['city'],
                        'region' => $clubData['region'],
                        'marketplace_summary' => $clubData['summary'],
                    ],
                ]
            );

            TenantDomain::updateOrCreate(
                ['domain' => $clubData['slug'].'.'.$rootDomain],
                [
                    'tenant_id' => $tenant->id,
                    'type' => TenantDomain::TYPE_PLATFORM_SUBDOMAIN,
                    'is_primary' => true,
                    'status' => TenantDomain::STATUS_ACTIVE,
                    'verified_at' => now(),
                    'ssl_status' => 'active',
                    'dns_status' => 'active',
                    'redirect_to_primary' => false,
                ]
            );

            TenantBranding::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'primary_color' => $this->themePrimary($clubData['theme']),
                    'accent_color' => $this->themeAccent($clubData['theme']),
                    'show_platform_branding' => true,
                    'theme' => ['preset' => $clubData['theme']],
                ]
            );

            $home = CmsPage::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'home'],
                [
                    'title' => 'Home',
                    'seo_title' => $clubData['name'].' — Private Community',
                    'seo_description' => $clubData['summary'],
                    'status' => 'published',
                    'is_homepage' => true,
                    'published_at' => now(),
                ]
            );

            $sections = [
                ['name'=>'showcase-hero','type'=>'hero','sort_order'=>10,'content'=>['eyebrow'=>'WELCOME TO '.$clubData['name'],'heading'=>'A private community with its own point of view.','body'=>$clubData['summary'],'primary_label'=>'Explore Events','primary_url'=>'/events']],
                ['name'=>'showcase-events','type'=>'event_grid','sort_order'=>20,'content'=>['heading'=>'Upcoming at '.$clubData['name'],'limit'=>6]],
                ['name'=>'showcase-story','type'=>'image_text','sort_order'=>30,'content'=>['heading'=>'Made for real community','body'=>'Membership, events and conversations live together here, with privacy controls designed for a private adult community rather than a public social feed.']],
                ['name'=>'showcase-membership','type'=>'cta','sort_order'=>40,'content'=>['heading'=>'Interested in joining?','body'=>'Learn about this community, review upcoming events and submit a membership application when you are ready.','button_label'=>'Apply to Join','button_url'=>'/membership/apply']],
            ];

            foreach ($sections as $section) {
                CmsSection::updateOrCreate(
                    ['cms_page_id' => $home->id, 'name' => $section['name']],
                    [
                        'type' => $section['type'],
                        'content' => $section['content'],
                        'settings' => [],
                        'sort_order' => $section['sort_order'],
                        'is_enabled' => true,
                    ]
                );
            }

            foreach ($clubData['events'] as $eventIndex => [$title, $category, $summary]) {
                $offset = 4 + ($index * 3) + ($eventIndex * 8);
                $start = now()->startOfDay()->addDays($offset)->setTime($eventIndex === 0 ? 20 : 19, 0);
                $slug = Str::slug($clubData['slug'].'-'.$title);

                Event::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $slug],
                    [
                        'title' => $title,
                        'summary' => $summary,
                        'description' => $summary."\n\nThis showcase listing demonstrates how a complete Private Gather tenant event appears in discovery, RSVP and admission workflows.",
                        'visibility' => 'public',
                        'rsvp_mode' => $eventIndex === 0 ? 'approval' : 'instant',
                        'status' => 'published',
                        'starts_at' => $start,
                        'ends_at' => (clone $start)->addHours($eventIndex === 0 ? 5 : 4),
                        'timezone' => $clubData['timezone'],
                        'capacity' => 80 + ($index * 12) + ($eventIndex * 25),
                        'city' => $clubData['city'],
                        'region' => $clubData['region'],
                        'public_location_label' => $clubData['city'].', '.$clubData['region'],
                        'exact_address' => null,
                        'exact_address_visibility' => 'approved_attendees',
                        'category' => $category,
                        'dress_code' => $eventIndex === 0 ? 'Upscale evening attire' : 'Smart casual / club appropriate',
                        'rules' => 'Respect consent, privacy and the host community’s posted expectations. No unauthorized photography or sharing of private attendee information.',
                        'waitlist_enabled' => true,
                        'requires_verified_profile' => false,
                        'registration_opens_at' => now()->subDay(),
                        'registration_closes_at' => (clone $start)->subHours(2),
                    ]
                );
            }
        }
    }

    private function themePrimary(string $theme): string
    {
        return match ($theme) {
            'art-deco-house' => '#d3ad5d', 'neon-underground' => '#e748ff', 'resort-night' => '#7fcbd0',
            'gallery-minimal' => '#171717', 'garden-estate' => '#d5bb77', 'industrial-loft' => '#d08a45',
            'cabaret-rouge' => '#e1b567', 'private-villa' => '#6b2c2d', 'social-club' => '#7ee0ce',
            default => '#c49a54',
        };
    }

    private function themeAccent(string $theme): string
    {
        return match ($theme) {
            'art-deco-house' => '#6d572d', 'neon-underground' => '#7920ac', 'resort-night' => '#1b7886',
            'gallery-minimal' => '#6c625a', 'garden-estate' => '#315740', 'industrial-loft' => '#5b6268',
            'cabaret-rouge' => '#8b1d30', 'private-villa' => '#8c7261', 'social-club' => '#365e69',
            default => '#6f2948',
        };
    }
}
