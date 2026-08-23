@extends('layouts.app')
@section('title', 'Trust & Recognition')
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">TRUST & RECOGNITION</span><h1>Know who issued the signal.</h1><p>Private Gather recognition and club-specific badges stay visually separated so trust context is never ambiguous.</p></div></section>
<section class="pg-page"><div class="pg-shell pg-grid pg-grid-2">
    <section class="pg-card"><span class="pg-eyebrow">PLATFORM</span><div class="pg-section-head"><div><h2>Private Gather badges</h2><p>Recognition issued at platform scope.</p></div><span class="pg-pill">Private Gather issuer</span></div>
        <div class="pg-grid">@forelse($globalBadges as $assignment)<article class="pg-card" style="background:rgba(255,255,255,.025)!important"><div class="row-between"><div><div class="pg-event-meta"><span class="pg-pill">PG · {{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span><span class="pg-pill">{{ ucfirst($assignment->badge->category) }}</span></div><p class="muted">{{ $assignment->badge->description ?: 'Private Gather recognition.' }}</p></div></div><small class="muted">issued by Private Gather @if($assignment->expires_at)· expires {{ $assignment->expires_at->format('M j, Y') }}@endif</small></article>@empty<div class="pg-empty">No active platform badges.</div>@endforelse</div>
    </section>
    <section class="pg-card"><span class="pg-eyebrow">COMMUNITIES</span><div class="pg-section-head"><div><h2>Club & organization badges</h2><p>Recognition applies only in the issuing community’s context.</p></div></div>
        <div class="pg-grid">@forelse($tenantBadges as $tenantId => $assignments) @php($issuer=$assignments->first()?->tenant?->name ?? $assignments->first()?->badge?->tenant?->name ?? 'Club / Organization') <article class="pg-card" style="background:rgba(255,255,255,.025)!important"><div class="row-between"><div><span class="pg-pill">{{ $issuer }}</span><h3 style="margin:.75rem 0">{{ $issuer }} recognition</h3></div></div><div class="pg-trust-row">@foreach($assignments as $assignment)<span class="pg-pill">{{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span>@endforeach</div><p class="muted" style="margin-top:14px">issued by {{ $issuer }}. Club-issued recognition is not represented as a Private Gather platform credential.</p></article>@empty<div class="pg-empty">No active club-issued badges.</div>@endforelse</div>
    </section>
</div>
<div class="pg-shell" style="margin-top:18px"><div class="pg-privacy-note">Badges communicate their configured recognition only. They should not be interpreted as identity, age or safety guarantees unless the issuing workflow explicitly establishes that claim.</div></div>
</section>
@endsection
