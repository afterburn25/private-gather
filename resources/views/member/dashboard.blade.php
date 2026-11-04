@extends('layouts.app')
@section('title', 'My Private Gather')
@section('content')
@php
    $isHosted = \App\Support\Edition::isHosted();
    $currentTenant = app(\App\Tenancy\TenantContext::class)->tenant();
    $isCentral = $currentTenant === null;
@endphp
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">MY PRIVATE GATHER</span>
        <h1>Welcome back, {{ $user->display_name ?: $user->name }}.</h1>
        <p>Your private social workspace for discovery, communities, conversations, memberships, tickets, trust and account controls.</p>
        <div class="pg-actions" style="margin-top:22px">
            <a class="button button-primary" href="{{ \Illuminate\Support\Facades\Route::has('discover.index') ? route('discover.index') : route('site.events') }}">Discover</a>
            @if($isHosted && $isCentral && \Illuminate\Support\Facades\Route::has('network-community.index'))<a class="button button-ghost" href="{{ route('network-community.index') }}">Community</a>@endif
            @if($isHosted)<a class="button button-ghost" href="{{ route('site.organizations') }}">Browse clubs</a>@endif
            <a class="button button-ghost" href="{{ route('messages.index') }}">Messages</a>
            @if($isHosted)<a class="button button-ghost" href="{{ route('organizations.index') }}">My clubs</a>@endif
        </div>
    </div>
</section>
<section class="pg-page"><div class="pg-shell">
    <div class="pg-grid pg-grid-4">
        <div class="pg-stat"><span>Recent RSVPs</span><strong>{{ $rsvps->count() }}</strong></div>
        <div class="pg-stat"><span>Approved plans</span><strong>{{ $rsvps->where('status','approved')->count() }}</strong></div>
        <div class="pg-stat"><span>Communities</span><strong>{{ $user->tenants->count() }}</strong></div>
        <div class="pg-stat"><span>Active badges</span><strong>{{ $badges->count() }}</strong></div>
    </div>

    <section class="pg-section">
        <div class="pg-section-head"><div><span class="pg-eyebrow">MEMBER WORKSPACE</span><h2>Everything in one place</h2><p>The full member product is surfaced here instead of being hidden behind unlinked routes.</p></div></div>
        <div class="pg-grid pg-grid-4">
            <a class="pg-card pg-card-interactive" href="{{ \Illuminate\Support\Facades\Route::has('discover.index') ? route('discover.index') : route('site.events') }}"><span class="pg-pill">Discover</span><h3>Find your next experience</h3><p class="muted">Recommended events, clubs and private communities.</p></a>
            <a class="pg-card pg-card-interactive" href="{{ route('site.events') }}"><span class="pg-pill">Events</span><h3>Events</h3><p class="muted">Browse upcoming experiences and manage your plans.</p></a>
            @if($isHosted)<a class="pg-card pg-card-interactive" href="{{ route('site.organizations') }}"><span class="pg-pill">Clubs</span><h3>Clubs & organizations</h3><p class="muted">Explore verified communities and their events.</p></a>@endif
            @if($isHosted && $isCentral && \Illuminate\Support\Facades\Route::has('network-community.index'))<a class="pg-card pg-card-interactive" href="{{ route('network-community.index') }}"><span class="pg-pill">Community</span><h3>Private Gather community</h3><p class="muted">Join the members-only network feed without crossing into a club’s private wall.</p></a>@endif
            @if($isHosted && $isCentral && \Illuminate\Support\Facades\Route::has('network-community.groups.index'))<a class="pg-card pg-card-interactive" href="{{ route('network-community.groups.index') }}"><span class="pg-pill">Groups</span><h3>Network groups</h3><p class="muted">Create or join interest groups across Private Gather.</p></a>@elseif(!$isCentral && \Illuminate\Support\Facades\Route::has('community.groups.index'))<a class="pg-card pg-card-interactive" href="{{ route('community.groups.index') }}"><span class="pg-pill">Groups</span><h3>Club groups</h3><p class="muted">Join conversations and smaller communities inside this club context.</p></a>@endif
            @if(\Illuminate\Support\Facades\Route::has('members.discover'))<a class="pg-card pg-card-interactive" href="{{ route('members.discover') }}"><span class="pg-pill">Members</span><h3>Member discovery</h3><p class="muted">{{ $isCentral ? 'Discover Private Gather members within their individual privacy choices.' : 'Discover people only within the tenant privacy scope you are allowed to see.' }}</p></a>@endif
            <a class="pg-card pg-card-interactive" href="{{ route('messages.index') }}"><span class="pg-pill">Messages</span><h3>Messages</h3><p class="muted">Private conversations with mute and live polling support.</p></a>
            @if(\Illuminate\Support\Facades\Route::has('saved.index'))<a class="pg-card pg-card-interactive" href="{{ route('saved.index') }}"><span class="pg-pill">Saved</span><h3>Saved</h3><p class="muted">Favorites and saved discovery searches.</p></a>@endif
            @if(\Illuminate\Support\Facades\Route::has('notifications.index'))<a class="pg-card pg-card-interactive" href="{{ route('notifications.index') }}"><span class="pg-pill">Alerts</span><h3>Notifications</h3><p class="muted">Notification center, preferences and browser push enrollment.</p></a>@endif
            @if(\Illuminate\Support\Facades\Route::has('connections.index'))<a class="pg-card pg-card-interactive" href="{{ route('connections.index') }}"><span class="pg-pill">Connections</span><h3>Connections & following</h3><p class="muted">Requests, follows and blocking controls.</p></a>@endif
            @if(\Illuminate\Support\Facades\Route::has('member.membership.show'))<a class="pg-card pg-card-interactive" href="{{ route('member.membership.show') }}"><span class="pg-pill">Membership</span><h3>My memberships</h3><p class="muted">Membership status, terms, renewals and subscription lifecycle.</p></a>@endif
            <a class="pg-card pg-card-interactive" href="{{ route('member.badges.index') }}"><span class="pg-pill">Trust</span><h3>My badges</h3><p class="muted">Platform and club recognition with issuer context.</p></a>
            <a class="pg-card pg-card-interactive" href="{{ route('member.tickets') }}"><span class="pg-pill">Admission</span><h3>Tickets</h3><p class="muted">Your event admission records and ticket history.</p></a>
            <a class="pg-card pg-card-interactive" href="{{ route('member.orders.index') }}"><span class="pg-pill">Commerce</span><h3>Orders</h3><p class="muted">Review purchase and payment history.</p></a>
            @if(\Illuminate\Support\Facades\Route::has('privacy.index'))<a class="pg-card pg-card-interactive" href="{{ route('privacy.index') }}"><span class="pg-pill">Privacy</span><h3>Privacy Control Center</h3><p class="muted">Control visibility and privacy preferences.</p></a>@endif
            <a class="pg-card pg-card-interactive" href="{{ route('member.security') }}"><span class="pg-pill">Security</span><h3>Security</h3><p class="muted">2FA, session controls and security activity.</p></a>
            @if(\Illuminate\Support\Facades\Route::has('onboarding.index'))<a class="pg-card pg-card-interactive" href="{{ route('onboarding.index') }}"><span class="pg-pill">Preferences</span><h3>Onboarding & preferences</h3><p class="muted">Keep discovery and communication preferences current.</p></a>@endif
        </div>
    </section>

    <section class="pg-section"><div class="pg-grid pg-grid-2">
        <article class="pg-card">
            <div class="pg-section-head"><div><span class="pg-eyebrow">TRUST & RECOGNITION</span><h2>Your badges</h2><p>Issuer context stays visible so club recognition is never confused with platform recognition.</p></div><a class="text-link" href="{{ route('member.badges.index') }}">View all →</a></div>
            <div class="pg-trust-row">@forelse($badges->take(8) as $assignment)<span class="pg-pill">{{ $assignment->badge->isGlobal() ? 'PG · ' : '' }}{{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span>@empty<span class="muted">No active badges yet.</span>@endforelse</div>
        </article>
        <article class="pg-card">
            <span class="pg-eyebrow">PRIVACY CHECK</span><h2>Your visibility is yours.</h2><p class="muted">Your account may belong to multiple communities, but membership in one does not automatically expose activity to another.</p><div class="pg-actions">@if(\Illuminate\Support\Facades\Route::has('privacy.index'))<a class="button button-ghost" href="{{ route('privacy.index') }}">Privacy controls</a>@else<a class="button button-ghost" href="{{ route('profile.edit') }}">Profile visibility</a>@endif<a class="button button-ghost" href="{{ route('member.security') }}">Security</a></div>
        </article>
    </section>

    <section class="pg-section">
        <div class="pg-section-head"><div><span class="pg-eyebrow">YOUR PLANS</span><h2>Upcoming & recent events</h2><p>RSVP state remains tied to the host’s admission workflow.</p></div><a class="text-link" href="{{ route('site.events') }}">Find another event →</a></div>
        <div class="pg-grid pg-grid-2">
            @forelse($rsvps as $rsvp)
                <article class="pg-card pg-card-interactive"><div class="row-between"><div><span class="pg-pill">{{ ucfirst($rsvp->status) }}</span><h3 style="font-size:1.35rem;margin:.7rem 0 .25rem">{{ $rsvp->event?->title }}</h3><p class="muted">{{ $rsvp->event?->starts_at?->format('M j, Y · g:i A') }}</p></div>@if($rsvp->event)<a class="button button-ghost" href="{{ route('events.show',$rsvp->event) }}">View</a>@endif</div></article>
            @empty
                <div class="pg-empty"><h3>No RSVPs yet</h3><p>Your upcoming plans will stay organized here.</p><a class="button button-primary" href="{{ route('site.events') }}">Discover events</a></div>
            @endforelse
        </div>
    </section>
</div></section>
@endsection