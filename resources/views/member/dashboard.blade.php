@extends('layouts.app')
@section('title', 'My Private Gather')
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">MY PRIVATE GATHER</span>
        <h1>Welcome back, {{ $user->display_name ?: $user->name }}.</h1>
        <p>Your memberships, upcoming plans, recognition and private account controls in one discreet place.</p>
        <div class="pg-actions" style="margin-top:22px"><a class="button button-primary" href="{{ route('site.events') }}">Discover events</a><a class="button button-ghost" href="{{ route('messages.index') }}">Messages</a><a class="button button-ghost" href="{{ route('profile.edit') }}">Profile & privacy</a></div>
    </div>
</section>
<section class="pg-page"><div class="pg-shell">
    <div class="pg-grid pg-grid-4">
        <div class="pg-stat"><span>Recent RSVPs</span><strong>{{ $rsvps->count() }}</strong></div>
        <div class="pg-stat"><span>Approved plans</span><strong>{{ $rsvps->where('status','approved')->count() }}</strong></div>
        <div class="pg-stat"><span>Communities</span><strong>{{ $user->tenants->count() }}</strong></div>
        <div class="pg-stat"><span>Active badges</span><strong>{{ $badges->count() }}</strong></div>
    </div>

    <section class="pg-section"><div class="pg-grid pg-grid-2">
        <article class="pg-card">
            <div class="pg-section-head"><div><span class="pg-eyebrow">TRUST & RECOGNITION</span><h2>Your badges</h2><p>Issuer context stays visible so club recognition is never confused with platform recognition.</p></div><a class="text-link" href="{{ route('member.badges.index') }}">View all →</a></div>
            <div class="pg-trust-row">@forelse($badges->take(8) as $assignment)<span class="pg-pill">{{ $assignment->badge->isGlobal() ? 'PG · ' : '' }}{{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span>@empty<span class="muted">No active badges yet.</span>@endforelse</div>
        </article>
        <article class="pg-card">
            <span class="pg-eyebrow">PRIVACY CHECK</span><h2>Your visibility is yours.</h2><p class="muted">Your account may belong to multiple communities, but membership in one does not automatically expose activity to another.</p><div class="pg-actions"><a class="button button-ghost" href="{{ route('profile.edit') }}">Profile visibility</a><a class="button button-ghost" href="{{ route('member.security') }}">Security</a></div>
        </article>
    </div></section>

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
