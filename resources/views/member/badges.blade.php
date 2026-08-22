@extends('layouts.app')
@section('title', 'My Badges')
@section('content')
<section class="container section">
    <div class="eyebrow">TRUST & RECOGNITION</div><h1>My Badges</h1><p class="muted">Private Gather badges and club-specific recognition are shown separately so the issuer is always clear.</p>
    <section class="panel"><h2>Private Gather badges</h2>@forelse($globalBadges as $assignment)<div class="list-row"><div><strong><span class="pill">PG {{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span></strong><small>{{ $assignment->badge->description ?: ucfirst($assignment->badge->category) }} · issued by Private Gather @if($assignment->expires_at)· expires {{ $assignment->expires_at->format('M j, Y') }}@endif</small></div></div>@empty<p>No active global badges.</p>@endforelse</section>
    @forelse($tenantBadges as $tenantId => $assignments)
        @php($issuer=$assignments->first()?->tenant?->name ?? $assignments->first()?->badge?->tenant?->name ?? 'Club / Organization')
        <section class="panel"><h2>{{ $issuer }} badges</h2>@foreach($assignments as $assignment)<div class="list-row"><div><strong><span class="pill">{{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span></strong><small>{{ $assignment->badge->description ?: ucfirst($assignment->badge->category) }} · issued by {{ $issuer }} @if($assignment->expires_at)· expires {{ $assignment->expires_at->format('M j, Y') }}@endif</small></div></div>@endforeach</section>
    @empty
    @endforelse
</section>
@endsection
