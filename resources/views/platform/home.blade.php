@extends('layouts.app')
@section('title', ($platformContent['brand_name'] ?? config('app.name')).' — Private lifestyle communities, events and clubs')
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell pg-grid pg-grid-2" style="align-items:center">
        <div>
            <span class="pg-eyebrow">{{ $platformContent['hero_eyebrow'] ?? 'PRIVATE BY NATURE' }}</span>
            <h1 class="pg-display">{{ $platformContent['hero_heading'] ?? 'Find the right room, not the biggest crowd.' }}</h1>
            <p class="pg-lead">{{ $platformContent['hero_body'] ?? 'Discover discreet lifestyle events, trusted clubs and private communities built around consent, compatibility and belonging.' }}</p>
            <div class="pg-actions" style="margin-top:26px">
                <a class="button button-primary button-large" href="{{ \App\Support\MountUrl::to($platformContent['hero_primary_url'] ?? route('site.events')) }}">{{ $platformContent['hero_primary_label'] ?? 'Discover events' }}</a>
                <a class="button button-ghost button-large" href="{{ \App\Support\MountUrl::to($platformContent['hero_secondary_url'] ?? route('site.organizations')) }}">{{ $platformContent['hero_secondary_label'] ?? 'Explore clubs' }}</a>
            </div>
            <div class="pg-trust-row" style="margin-top:24px">
                <span class="pg-pill">18+ only</span>
                <span class="pg-pill">Privacy-first</span>
                <span class="pg-pill">Consent-led communities</span>
            </div>
        </div>
        <div>
            @if(!empty($platformContent['hero_image_url']))
                <div class="pg-media" style="min-height:430px"><img src="{{ \App\Support\MountUrl::to($platformContent['hero_image_url']) }}" alt=""><div class="pg-media-overlay"><span class="pg-eyebrow">PRIVATE GATHER</span><h2>Go where you feel welcome.</h2></div></div>
            @else
                <form class="pg-card" action="{{ route('site.events') }}">
                    <span class="pg-eyebrow">DISCOVER YOUR NIGHT</span>
                    <h2 style="margin:.45rem 0 1.1rem">What are you looking for?</h2>
                    <label>Keyword<input name="q" placeholder="Party, social, takeover, pool night"></label>
                    <label>City<input name="city" placeholder="City or metro area"></label>
                    <label>Category<input name="category" placeholder="Event type"></label>
                    <button class="button button-primary" style="width:100%;margin-top:12px">Explore events</button>
                    <p class="pg-privacy-note" style="margin-top:15px">Exact private-event locations stay protected until the host chooses to reveal them.</p>
                </form>
            @endif
        </div>
    </div>
</section>

<section class="pg-section">
    <div class="pg-shell">
        <div class="pg-section-head"><div><span class="pg-eyebrow">START WITH INTENT</span><h2>Find your kind of community</h2><p>Private Gather is organized around how people actually participate.</p></div></div>
        <div class="pg-grid pg-grid-4">
            <a class="pg-card pg-card-interactive" href="{{ route('site.events') }}"><span class="pg-pill">Tonight & upcoming</span><h3>Events</h3><p class="muted">Parties, socials, trips, takeovers and members-only experiences.</p></a>
            <a class="pg-card pg-card-interactive" href="{{ route('site.organizations') }}"><span class="pg-pill">Established spaces</span><h3>Clubs</h3><p class="muted">Explore lifestyle clubs, venues and communities with their own standards and membership process.</p></a>
            <a class="pg-card pg-card-interactive" href="{{ route('site.organizations') }}"><span class="pg-pill">Community-led</span><h3>Organizers</h3><p class="muted">Follow trusted hosts and recurring event brands.</p></a>
            @auth
                <a class="pg-card pg-card-interactive" href="{{ route('dashboard') }}"><span class="pg-pill">Your world</span><h3>My Private Gather</h3><p class="muted">Tickets, RSVPs, memberships, badges and private conversations in one place.</p></a>
            @else
                <a class="pg-card pg-card-interactive" href="{{ route('register') }}"><span class="pg-pill">Your world</span><h3>Join privately</h3><p class="muted">Create one account for the clubs, groups and events you choose to participate in.</p></a>
            @endauth
        </div>
    </div>
</section>

@if(($platformContent['show_featured_events'] ?? '1') === '1')
<section class="pg-section">
    <div class="pg-shell">
        <div class="pg-section-head"><div><span class="pg-eyebrow">{{ $platformContent['featured_eyebrow'] ?? 'CURATED DISCOVERY' }}</span><h2>{{ $platformContent['featured_heading'] ?? 'Upcoming experiences' }}</h2></div><a class="text-link" href="{{ route('site.events') }}">View all events →</a></div>
        <div class="pg-grid pg-grid-3">
            @forelse($events as $event)
                <article class="pg-card pg-card-interactive pg-event-card">
                    <div class="pg-media">@if($event->cover_image_path)<img src="{{ \App\Support\MountUrl::to($event->cover_image_path) }}" alt="{{ $event->title }} showcase image">@endif<div class="pg-media-overlay"><span class="pg-pill">{{ $event->starts_at->format('M j') }}</span></div></div>
                    <div class="pg-event-body">
                        <div class="pg-event-meta"><span class="pg-pill">{{ $event->public_location_label ?: $event->city ?: 'Location protected' }}</span>@if($event->category)<span class="pg-pill">{{ $event->category }}</span>@endif</div>
                        <h3 class="pg-event-title">{{ $event->title }}</h3>
                        <p class="pg-event-copy">{{ $event->starts_at->format('D, M j · g:i A') }}<br>Hosted by {{ $event->tenant->name }}</p>
                        <a class="text-link" href="{{ route('events.show', $event) }}">View event →</a>
                    </div>
                </article>
            @empty
                <div class="pg-empty"><h3>No public events yet</h3><p>Published events will appear here without exposing private location details.</p></div>
            @endforelse
        </div>
    </div>
</section>
@endif

@if(($platformContent['show_organizations'] ?? '1') === '1')
<section class="pg-section">
    <div class="pg-shell">
        <div class="pg-section-head"><div><span class="pg-eyebrow">{{ $platformContent['organizations_eyebrow'] ?? 'TRUSTED SPACES' }}</span><h2>{{ $platformContent['organizations_heading'] ?? 'Clubs & organizers' }}</h2><p>Each community sets its own membership, admission and privacy standards.</p></div><a class="text-link" href="{{ route('site.organizations') }}">Browse all →</a></div>
        <div class="pg-grid pg-grid-3">
            @forelse($organizations as $org)
                <article class="pg-card pg-card-interactive">
                    @if(data_get($org->settings,'cover_image_path'))<div class="pg-media" style="margin-bottom:16px"><img src="{{ \App\Support\MountUrl::to(data_get($org->settings,'cover_image_path')) }}" alt="{{ $org->name }} showcase image"><div class="pg-media-overlay">@if(data_get($org->settings,'showcase_content'))<span class="pg-pill">Showcase</span>@endif</div></div>@endif
                    <span class="pg-pill">{{ str_replace('_', ' ', ucfirst($org->type)) }}</span>
                    <h3 style="font-size:1.45rem;margin:.8rem 0 .35rem">{{ $org->name }}</h3>
                    <p class="muted">{{ $org->primaryDomain?->domain ?: 'Private Gather hosted community' }}</p>
                    @if($org->primaryDomain)<a class="text-link" href="https://{{ $org->primaryDomain->domain }}">Visit community →</a>@endif
                </article>
            @empty
                <div class="pg-empty"><h3>Communities are being prepared</h3><p>Approved clubs and organizers will appear here.</p></div>
            @endforelse
        </div>
    </div>
</section>
@endif

<section class="pg-section">
    <div class="pg-shell pg-card" style="padding:clamp(26px,5vw,54px)">
        <div class="pg-grid pg-grid-2" style="align-items:center">
            <div><span class="pg-eyebrow">BUILT FOR DISCRETION</span><h2 style="font-size:clamp(2rem,5vw,4rem);line-height:1;letter-spacing:-.05em;margin:.45rem 0">Privacy should be structural.</h2></div>
            <div><p class="pg-lead">Private events can protect exact locations, clubs can screen membership, and members choose where their profile appears. The platform is designed around consent and context rather than public exposure.</p><a class="text-link" href="{{ route('site.about') }}">How Private Gather works →</a></div>
        </div>
    </div>
</section>
@endsection
