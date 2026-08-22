@extends('layouts.app')
@section('title', 'Private Gather — Exclusive events, private communities & real connections')
@section('meta_description', 'Discover curated adults-only lifestyle events, private communities and trusted social experiences for open-minded adults through Private Gather.')
@section('content')
@php
    $showcaseEvents = collect([
        [
            'slug' => 'velvet-masquerade-mixer',
            'title' => 'Velvet Masquerade Mixer',
            'venue' => 'The Beekman',
            'location' => 'New York, NY',
            'category' => 'Social',
            'image' => '/assets/luxury/event-founders-dinner.webp',
            'fallback_date' => now()->addDays(14),
        ],
        [
            'slug' => 'amalfi-couples-escape',
            'title' => 'Amalfi Couples Escape',
            'venue' => 'Private coastal villa',
            'location' => 'Positano, Italy',
            'category' => 'Lifestyle',
            'image' => '/assets/luxury/event-amalfi-retreat.webp',
            'fallback_date' => now()->addDays(28),
        ],
        [
            'slug' => 'desire-dialogues-live',
            'title' => 'Desire Dialogues Live',
            'venue' => 'Private members salon',
            'location' => 'London, UK',
            'category' => 'Learning',
            'image' => '/assets/luxury/event-dialogues-live.webp',
            'fallback_date' => now()->addDays(42),
        ],
        [
            'slug' => 'sunset-yacht-social',
            'title' => 'Sunset Yacht Social',
            'venue' => 'Marina del Rey',
            'location' => 'Los Angeles, CA',
            'category' => 'Social',
            'image' => '/assets/luxury/event-yacht-social.webp',
            'fallback_date' => now()->addDays(56),
        ],
    ]);

    $showcaseClubs = collect([
        [
            'slug' => 'velvet-circle',
            'title' => 'Velvet Circle',
            'description' => 'A Private Gather house community for adventurous couples and singles who value discretion, style and meaningful connection.',
            'image' => '/assets/luxury/club-velvet-circle.webp',
            'meta' => 'Private Gather house community',
            'icon' => '♛',
        ],
        [
            'slug' => 'founders-after-dark',
            'title' => 'Founders After Dark',
            'description' => 'A polished after-hours community blending private socials, elevated conversation and invitation-only experiences.',
            'image' => '/assets/luxury/club-after-dark.webp',
            'meta' => 'Curated professional circle',
            'icon' => '◇',
        ],
        [
            'slug' => 'wellness-play-society',
            'title' => 'Wellness & Play Society',
            'description' => 'Retreat-minded members balancing wellness, travel, connection and adults-only lifestyle experiences.',
            'image' => '/assets/luxury/club-wellness.webp',
            'meta' => 'Retreat & wellness community',
            'icon' => '◈',
        ],
        [
            'slug' => 'global-explorers',
            'title' => 'Global Explorers',
            'description' => 'A worldwide Private Gather community for open-minded travelers, destination events and refined social adventures.',
            'image' => '/assets/luxury/club-global-explorers.webp',
            'meta' => 'Travel & destination community',
            'icon' => '◎',
        ],
    ]);
@endphp

<section class="lux-hero" id="home">
    <div class="lux-hero-media" aria-hidden="true">
        <img src="{{ \App\Support\MountUrl::to('/assets/luxury/hero-rooftop.webp') }}" alt="">
    </div>
    <div class="lux-hero-shade" aria-hidden="true"></div>
    <div class="container lux-hero-inner">
        <div class="lux-hero-copy">
            <span class="lux-kicker">BY INVITATION. BY DESIRE. BY YOU.</span>
            <h1>Exclusive events.<br>Private communities.<br><em>Real connections.</em></h1>
            <p>Private Gather is your gateway to curated adults-only experiences, private communities and meaningful connections for open-minded adults.</p>
            <div class="lux-actions">
                <a class="lux-button lux-button-gold" href="{{ route('register') }}">Join Now</a>
                <a class="lux-button lux-button-outline" href="{{ route('site.events') }}"><span class="lux-play">▶</span> Explore Events</a>
            </div>
        </div>
    </div>
</section>

@include('partials.ad-leaderboard',[
    'adSlot'=>'home_hero_leaderboard',
    'adOffer'=>isset($affiliateOffers) ? $affiliateOffers->get(1) : null,
])

<section class="lux-section lux-section-tight" id="events">
    <div class="container">
        <div class="lux-section-heading">
            <span>FEATURED EVENTS</span>
            <i></i>
            <a href="{{ route('site.events') }}">View all events →</a>
        </div>
        <div class="lux-card-grid lux-event-grid">
            @foreach($showcaseEvents as $cardIndex => $card)
                @php($liveEvent = $events->firstWhere('slug', $card['slug']))
                @php($eventDate = $liveEvent?->starts_at ?: $card['fallback_date'])
                <article class="lux-event-card">
                    <a href="{{ $liveEvent ? route('events.show', $liveEvent) : route('site.events') }}">
                        <div class="lux-card-photo">
                            <img src="{{ \App\Support\MountUrl::to($card['image']) }}" alt="{{ $card['title'] }}">
                            <span class="lux-date-badge"><b>{{ strtoupper($eventDate->format('M')) }}</b>{{ $eventDate->format('d') }}</span>
                        </div>
                        <div class="lux-card-body">
                            <h3>{{ $liveEvent?->title ?: $card['title'] }}</h3>
                            <p><span class="lux-pin">◇</span> {{ $liveEvent?->public_location_label ?: $card['venue'] }}, {{ $liveEvent?->city ?: $card['location'] }}</p>
                            <span class="lux-tag lux-tag-{{ strtolower($card['category']) }}">{{ $liveEvent?->category ?: $card['category'] }}</span>
                        </div>
                    </a>
                </article>
                @if($cardIndex === 1)
                    @include('partials.sponsored-event-card',[
                        'adOffer'=>isset($affiliateOffers) ? $affiliateOffers->first() : null,
                        'adPlacement'=>'home_featured_events',
                    ])
                @endif
            @endforeach
        </div>
    </div>
</section>

<section class="lux-section lux-section-tight" id="clubs">
    <div class="container">
        <div class="lux-section-heading">
            <span>PRIVATE CLUBS &amp; COMMUNITIES</span>
            <i></i>
            <a href="{{ route('clubs.index') }}">Explore communities →</a>
        </div>
        <div class="lux-card-grid lux-club-grid">
            @foreach($showcaseClubs as $card)
                @php($liveClub = $featuredClubs->first(fn($profile) => $profile->tenant?->slug === $card['slug']))
                <article class="lux-club-card">
                    <a href="{{ $liveClub ? route('clubs.show', $liveClub->tenant->slug) : route('clubs.index') }}">
                        <div class="lux-card-photo">
                            <img src="{{ \App\Support\MountUrl::to($card['image']) }}" alt="{{ $card['title'] }}">
                            <span class="lux-club-icon">{{ $card['icon'] }}</span>
                        </div>
                        <div class="lux-card-body">
                            <h3>{{ $liveClub?->displayName() ?: $card['title'] }}</h3>
                            <p>{{ $liveClub?->short_description ?: $card['description'] }}</p>
                            <span class="lux-club-meta">{{ $card['meta'] }}</span>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="lux-benefits" id="membership">
    <div class="container">
        <div class="lux-benefits-heading">MEMBERSHIP BENEFITS</div>
        <div class="lux-benefits-grid">
            <article><span class="lux-benefit-icon">☆</span><div><h3>Exclusive Access</h3><p>Invitation-only events and private communities in select destinations.</p></div></article>
            <article><span class="lux-benefit-icon">♧</span><div><h3>Meaningful Connections</h3><p>Meet open-minded adults who value communication, consent and discretion.</p></div></article>
            <article><span class="lux-benefit-icon">◇</span><div><h3>Premium Experiences</h3><p>Curated socials, luxury venues, retreats and unforgettable shared moments.</p></div></article>
            <article><span class="lux-benefit-icon">♢</span><div><h3>Privacy First</h3><p>Controlled profiles, mutual matching and protected event details by design.</p></div></article>
            <a class="lux-button lux-button-outline lux-benefit-cta" href="{{ route('site.about') }}">Learn More</a>
        </div>
    </div>
</section>

<section class="lux-section lux-testimonials">
    <div class="container">
        <div class="lux-section-heading">
            <span>WHAT OUR MEMBERS SAY</span>
            <i></i>
            <a href="{{ route('register') }}">Join the community →</a>
        </div>
        <div class="lux-testimonial-grid">
            <article>
                <span class="lux-testimonial-avatar lux-avatar-alex" role="img" aria-label="Private Gather member Alex"></span>
                <blockquote>“Private Gather opened the door to experiences and people we never knew existed. The privacy and quality make the difference.”</blockquote>
                <p>— Alex R.<small>Member</small></p>
            </article>
            <article>
                <span class="lux-testimonial-avatar lux-avatar-samantha" role="img" aria-label="Private Gather member Samantha"></span>
                <blockquote>“The events, the community, the energy—it feels polished, welcoming and intentional from the moment you arrive.”</blockquote>
                <p>— Samantha L.<small>Member</small></p>
            </article>
            <article>
                <span class="lux-testimonial-avatar lux-avatar-michael" role="img" aria-label="Private Gather member Michael"></span>
                <blockquote>“Discretion, quality and genuinely interesting people. This is where real connections happen.”</blockquote>
                <p>— Michael T.<small>Member</small></p>
            </article>
        </div>
    </div>
</section>
@endsection
