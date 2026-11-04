{{-- PG-HOMEPAGE: CURATED-LUXURY-1.4.5 --}}
@extends('layouts.app')
@section('title', ($platformContent['brand_name'] ?? config('app.name')).' — Exclusive gatherings, clubs and private communities')
@section('content')
@php
    $mount = fn ($path) => \App\Support\MountUrl::to($path);
    $joinUrl = auth()->check()
        ? route('dashboard')
        : (\App\Support\Edition::registrationEnabled() ? route('register') : route('login'));
    $createClubUrl = auth()->check() && \Illuminate\Support\Facades\Route::has('organizations.create')
        ? route('organizations.create')
        : $joinUrl;

    $rawHero = is_scalar($platformContent['hero_image_url'] ?? null) ? trim((string) $platformContent['hero_image_url']) : '';
    $legacyHeroes = ['', '/assets/showcase/platform-hero.svg', 'assets/showcase/platform-hero.svg'];
    $heroPath = in_array($rawHero, $legacyHeroes, true) ? '/assets/showcase/home/hero-curated-night.jpg' : $rawHero;
    $heroImage = $mount($heroPath);

    $oldEyebrow = 'PRIVATE BY NATURE';
    $oldHeading = 'Find the right room, not the biggest crowd.';
    $oldBody = 'Discover discreet lifestyle events, trusted clubs and private communities built around consent, compatibility and belonging.';
    $oldPrimary = 'Discover events';
    $oldSecondary = 'Explore clubs';

    $heroEyebrowRaw = is_scalar($platformContent['hero_eyebrow'] ?? null) ? trim((string) $platformContent['hero_eyebrow']) : '';
    $heroHeadingRaw = is_scalar($platformContent['hero_heading'] ?? null) ? trim((string) $platformContent['hero_heading']) : '';
    $heroBodyRaw = is_scalar($platformContent['hero_body'] ?? null) ? trim((string) $platformContent['hero_body']) : '';
    $heroPrimaryLabelRaw = is_scalar($platformContent['hero_primary_label'] ?? null) ? trim((string) $platformContent['hero_primary_label']) : '';
    $heroSecondaryLabelRaw = is_scalar($platformContent['hero_secondary_label'] ?? null) ? trim((string) $platformContent['hero_secondary_label']) : '';

    $heroEyebrow = ($heroEyebrowRaw === '' || $heroEyebrowRaw === $oldEyebrow) ? 'Exclusivity Isn’t Invited.' : $heroEyebrowRaw;
    $heroHeading = ($heroHeadingRaw === '' || $heroHeadingRaw === $oldHeading) ? 'It’s Curated.' : $heroHeadingRaw;
    $heroBody = ($heroBodyRaw === '' || $heroBodyRaw === $oldBody)
        ? 'Private Gather is the premier platform for exclusive events, curated clubs and meaningful connections. Experience more. Belong deeply.'
        : $heroBodyRaw;
    $heroPrimaryLabel = ($heroPrimaryLabelRaw === '' || $heroPrimaryLabelRaw === $oldPrimary) ? 'Join Free' : $heroPrimaryLabelRaw;
    $heroSecondaryLabel = ($heroSecondaryLabelRaw === '' || $heroSecondaryLabelRaw === $oldSecondary) ? 'Browse Gatherings' : $heroSecondaryLabelRaw;

    $rawPrimaryUrl = is_scalar($platformContent['hero_primary_url'] ?? null) ? trim((string) $platformContent['hero_primary_url']) : '';
    $rawSecondaryUrl = is_scalar($platformContent['hero_secondary_url'] ?? null) ? trim((string) $platformContent['hero_secondary_url']) : '';
    $primaryUrl = ($heroPrimaryLabelRaw === '' || $heroPrimaryLabelRaw === $oldPrimary) ? $joinUrl : ($rawPrimaryUrl !== '' ? $mount($rawPrimaryUrl) : $joinUrl);
    $secondaryUrl = ($heroSecondaryLabelRaw === '' || $heroSecondaryLabelRaw === $oldSecondary) ? route('site.events') : ($rawSecondaryUrl !== '' ? $mount($rawSecondaryUrl) : route('site.events'));

    $eventFallbacks = [
        '/assets/showcase/home/event-masquerade-gala.jpg',
        '/assets/showcase/home/event-rooftop-sunset.jpg',
        '/assets/showcase/home/event-poolside-escape.jpg',
        '/assets/showcase/home/event-beachfire-weekend.jpg',
        '/assets/showcase/home/event-neon-dance-night.jpg',
        '/assets/showcase/home/event-intimate-lounge.jpg',
    ];
    $eventRules = [
        [['mask','masquerade','gala','formal','ball'], $eventFallbacks[0]],
        [['roof','rooftop','skyline','sunset','mixer','city'], $eventFallbacks[1]],
        [['pool','poolside','resort','tropical','swim'], $eventFallbacks[2]],
        [['beach','bonfire','fire','camp','coast','weekend'], $eventFallbacks[3]],
        [['dance','neon','nightclub','dj','music','party'], $eventFallbacks[4]],
        [['lounge','couple','intimate','cocktail','dinner','social'], $eventFallbacks[5]],
    ];
    $usedEventImages = [];
    $eventImageFor = function ($event, $index) use (&$usedEventImages, $eventFallbacks, $eventRules) {
        $custom = is_scalar($event->cover_image_path ?? null) ? trim((string) $event->cover_image_path) : '';
        if ($custom !== '' && ! in_array($custom, $usedEventImages, true)) {
            $usedEventImages[] = $custom;
            return $custom;
        }
        $haystack = strtolower(trim(implode(' ', array_filter([
            is_scalar($event->title ?? null) ? (string) $event->title : '',
            is_scalar($event->category ?? null) ? (string) $event->category : '',
            is_scalar($event->summary ?? null) ? (string) $event->summary : '',
            is_scalar($event->city ?? null) ? (string) $event->city : '',
        ]))));
        $preferred = null;
        foreach ($eventRules as [$terms, $path]) {
            foreach ($terms as $term) {
                if ($haystack !== '' && str_contains($haystack, $term)) {
                    $preferred = $path;
                    break 2;
                }
            }
        }
        if ($preferred && ! in_array($preferred, $usedEventImages, true)) {
            $usedEventImages[] = $preferred;
            return $preferred;
        }
        foreach ($eventFallbacks as $offset => $path) {
            $candidate = $eventFallbacks[($index + $offset) % count($eventFallbacks)];
            if (! in_array($candidate, $usedEventImages, true)) {
                $usedEventImages[] = $candidate;
                return $candidate;
            }
        }
        return $eventFallbacks[$index % count($eventFallbacks)];
    };

    $clubFallbacks = [
        '/assets/showcase/home/club-velvet-lounge.jpg',
        '/assets/showcase/home/club-azure-resort.jpg',
        '/assets/showcase/home/club-noir-nightlife.jpg',
        '/assets/showcase/home/club-heritage-house.jpg',
        '/assets/showcase/home/club-summit-retreat.jpg',
        '/assets/showcase/home/club-enigma-circle.jpg',
    ];
    $clubRules = [
        [['velvet','lounge','cocktail','elegant'], $clubFallbacks[0]],
        [['resort','azure','pool','beach','tropical','island'], $clubFallbacks[1]],
        [['noir','night','dance','club','euphoria'], $clubFallbacks[2]],
        [['heritage','library','house','society','classic'], $clubFallbacks[3]],
        [['summit','mountain','retreat','lodge','escape'], $clubFallbacks[4]],
        [['enigma','mystery','circle','private','secret'], $clubFallbacks[5]],
    ];
    $usedClubImages = [];
    $clubImageFor = function ($org, $index) use (&$usedClubImages, $clubFallbacks, $clubRules) {
        $settings = is_array($org->settings ?? null) ? $org->settings : [];
        $customCandidate = data_get($settings, 'cover_image_path');
        $custom = is_scalar($customCandidate) ? trim((string) $customCandidate) : '';
        if ($custom !== '' && ! in_array($custom, $usedClubImages, true)) {
            $usedClubImages[] = $custom;
            return $custom;
        }
        $tagline = data_get($settings, 'tagline');
        $description = data_get($settings, 'description');
        $haystack = strtolower(trim(implode(' ', array_filter([
            is_scalar($org->name ?? null) ? (string) $org->name : '',
            is_scalar($org->type ?? null) ? (string) $org->type : '',
            is_scalar($tagline) ? (string) $tagline : '',
            is_scalar($description) ? (string) $description : '',
        ]))));
        $preferred = null;
        foreach ($clubRules as [$terms, $path]) {
            foreach ($terms as $term) {
                if ($haystack !== '' && str_contains($haystack, $term)) {
                    $preferred = $path;
                    break 2;
                }
            }
        }
        if ($preferred && ! in_array($preferred, $usedClubImages, true)) {
            $usedClubImages[] = $preferred;
            return $preferred;
        }
        foreach ($clubFallbacks as $offset => $path) {
            $candidate = $clubFallbacks[($index + $offset) % count($clubFallbacks)];
            if (! in_array($candidate, $usedClubImages, true)) {
                $usedClubImages[] = $candidate;
                return $candidate;
            }
        }
        return $clubFallbacks[$index % count($clubFallbacks)];
    };
@endphp

<section class="pg-home-hero">
    <img class="pg-home-hero-bg" src="{{ $heroImage }}" alt="" fetchpriority="high" decoding="async">
    <div class="pg-home-hero-shade"></div>
    <div class="pg-shell pg-home-hero-inner">
        <div class="pg-home-hero-copy">
            <span class="pg-home-script">{{ $heroEyebrow }}</span>
            <h1>{{ $heroHeading }}</h1>
            <p>{{ $heroBody }}</p>
            <div class="pg-home-hero-actions">
                <a class="button button-primary button-large" href="{{ $primaryUrl }}">{{ $heroPrimaryLabel }}</a>
                <a class="button button-ghost button-large" href="{{ $secondaryUrl }}">{{ $heroSecondaryLabel }}</a>
                <a class="button button-ghost button-large" href="{{ $createClubUrl }}">Create a Club</a>
            </div>
        </div>
    </div>
</section>

@if(($platformContent['show_featured_events'] ?? '1') === '1')
<section class="pg-home-section pg-home-section-first">
    <div class="pg-shell">
        <div class="pg-home-section-head">
            <div><span class="pg-home-kicker">Featured Gatherings</span><h2>Upcoming Events You’ll Love</h2></div>
            <a class="pg-home-view-link" href="{{ route('site.events') }}">View all gatherings <span>→</span></a>
        </div>
        <div class="pg-home-card-grid pg-home-event-grid">
            @forelse($events->take(6) as $event)
                @php($eventImage = $mount($eventImageFor($event, $loop->index)))
                <article class="pg-home-card pg-home-event-card">
                    <a class="pg-home-card-media" href="{{ route('events.show', $event) }}">
                        <img src="{{ $eventImage }}" alt="{{ $event->title }}" loading="lazy" decoding="async">
                        <span class="pg-home-date"><small>{{ strtoupper($event->starts_at->format('M')) }}</small><strong>{{ $event->starts_at->format('d') }}</strong></span>
                    </a>
                    <div class="pg-home-card-body">
                        <h3><a href="{{ route('events.show', $event) }}">{{ $event->title }}</a></h3>
                        <p class="pg-home-card-meta">{{ $event->public_location_label ?: $event->city ?: 'Location protected' }}</p>
                        <p class="pg-home-card-date">{{ $event->starts_at->format('M j, Y') }}</p>
                        <a class="pg-home-card-link" href="{{ route('events.show', $event) }}">View event <span>→</span></a>
                    </div>
                </article>
            @empty
                <div class="pg-home-empty"><h3>Featured gatherings are coming soon.</h3><p>Published public events will appear here while exact private locations remain protected.</p><a class="button button-ghost" href="{{ route('site.events') }}">Browse gatherings</a></div>
            @endforelse
        </div>
    </div>
</section>
@endif

@if(($platformContent['show_organizations'] ?? '1') === '1')
<section class="pg-home-section">
    <div class="pg-shell">
        <div class="pg-home-section-head">
            <div><span class="pg-home-kicker">Featured Clubs</span><h2>Exclusive Clubs. Distinct Experiences.</h2></div>
            <a class="pg-home-view-link" href="{{ route('site.organizations') }}">View all clubs <span>→</span></a>
        </div>
        <div class="pg-home-card-grid pg-home-club-grid">
            @forelse($organizations->take(6) as $org)
                @php
                    $clubImage = $mount($clubImageFor($org, $loop->index));
                    $clubSettings = is_array($org->settings ?? null) ? $org->settings : [];
                    $clubCopyCandidate = data_get($clubSettings, 'tagline') ?: data_get($clubSettings, 'description');
                    $clubCopy = is_scalar($clubCopyCandidate) && trim((string) $clubCopyCandidate) !== ''
                        ? trim((string) $clubCopyCandidate)
                        : 'A curated private community with its own character, standards and experiences.';
                @endphp
                <article class="pg-home-card pg-home-club-card">
                    @if($org->primaryDomain)
                        <a class="pg-home-card-media" href="https://{{ $org->primaryDomain->domain }}"><img src="{{ $clubImage }}" alt="{{ $org->name }}" loading="lazy" decoding="async"></a>
                    @else
                        <div class="pg-home-card-media"><img src="{{ $clubImage }}" alt="{{ $org->name }}" loading="lazy" decoding="async"></div>
                    @endif
                    <div class="pg-home-card-body">
                        <h3>{{ $org->name }}</h3>
                        <p class="pg-home-club-copy">{{ \Illuminate\Support\Str::limit($clubCopy, 92) }}</p>
                        @if($org->primaryDomain)
                            <a class="pg-home-card-link" href="https://{{ $org->primaryDomain->domain }}">Explore club <span>→</span></a>
                        @else
                            <span class="pg-home-card-link pg-home-card-link-muted">Private Gather hosted</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pg-home-empty"><h3>Featured clubs are being curated.</h3><p>Approved clubs and organizers will appear here as they open their communities for discovery.</p><a class="button button-ghost" href="{{ route('site.organizations') }}">Browse clubs</a></div>
            @endforelse
        </div>
    </div>
</section>
@endif

<section class="pg-home-section pg-home-how">
    <div class="pg-shell">
        <div class="pg-home-center-head"><span class="pg-home-kicker">How It Works</span><h2>Your Exclusive World Awaits</h2></div>
        <div class="pg-home-steps">
            <div class="pg-home-step"><span class="pg-home-step-icon">♙</span><strong>1. Join</strong><p>Create your free profile and apply to access our community.</p></div>
            <div class="pg-home-step"><span class="pg-home-step-icon">⌕</span><strong>2. Discover</strong><p>Browse gatherings and clubs that match your lifestyle.</p></div>
            <div class="pg-home-step"><span class="pg-home-step-icon">▣</span><strong>3. Connect</strong><p>RSVP, meet like-minded members, and build connections.</p></div>
            <div class="pg-home-step"><span class="pg-home-step-icon">♢</span><strong>4. Belong</strong><p>Enjoy unforgettable experiences and become part of something exceptional.</p></div>
        </div>
    </div>
</section>

<section class="pg-home-benefits">
    <div class="pg-shell">
        <div class="pg-home-benefits-grid">
            <div class="pg-home-benefit-intro"><span class="pg-home-kicker">Member Benefits</span><h2>More Than Access.<br>It’s a Lifestyle.</h2><a class="button button-ghost" href="{{ $joinUrl }}">Join Free Today</a></div>
            <div class="pg-home-benefit"><span>♕</span><strong>Curated Quality</strong><p>Vetted gatherings and clubs selected for exceptional experiences.</p></div>
            <div class="pg-home-benefit"><span>♙</span><strong>Privacy First</strong><p>Your privacy and security are core to the platform.</p></div>
            <div class="pg-home-benefit"><span>◇</span><strong>Exclusive Access</strong><p>Invitations to premium events you won’t find everywhere else.</p></div>
            <div class="pg-home-benefit"><span>♧</span><strong>Meaningful Connections</strong><p>Meet like-minded adults in a respectful, elevated environment.</p></div>
            <div class="pg-home-benefit"><span>♢</span><strong>Concierge Support</strong><p>Personalized assistance to elevate your experience every step of the way.</p></div>
        </div>
    </div>
</section>

<section class="pg-home-final-cta">
    <div class="pg-shell pg-home-final-inner">
        <div><h2>Ready to Elevate Your Social Life?</h2><p>Join Private Gather and discover curated gatherings, distinctive clubs and meaningful connections.</p></div>
        <a class="button button-primary button-large" href="{{ $joinUrl }}">Join Free Now</a>
    </div>
</section>
@endsection
