@extends('layouts.app')
@section('title','Clubs & Organizers — '.config('app.name'))
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">COMMUNITIES</span>
        <h1>Find a place that fits.</h1>
        <p>Explore lifestyle clubs, organizers and established private communities. Each organization manages its own membership culture, event policies and privacy standards.</p>
        <div class="pg-trust-row" style="margin-top:20px"><span class="pg-pill">Independent communities</span><span class="pg-pill">Private membership options</span><span class="pg-pill">Branded club sites</span></div>
    </div>
</section>
<section class="pg-page">
    <div class="pg-shell">
        <div class="pg-section-head"><div><span class="pg-eyebrow">PRIVATE GATHER NETWORK</span><h2>{{ $organizations->total() }} {{ $organizations->total() === 1 ? 'community' : 'communities' }}</h2><p>Only organizations that choose marketplace visibility are listed here.</p></div></div>
        <div class="pg-grid pg-grid-3">
            @forelse($organizations as $org)
                @php
                    $city = data_get($org->settings, 'city');
                    $region = data_get($org->settings, 'region');
                    $location = trim(collect([$city,$region])->filter()->join(', '));
                    $kind = $org->isClub() ? 'Lifestyle club' : ($org->type === \App\Models\Tenant::TYPE_ORGANIZER ? 'Organizer' : 'Community');
                @endphp
                <article class="pg-card pg-card-interactive">
                    <div class="pg-event-meta"><span class="pg-pill">{{ $kind }}</span>@if($location)<span class="pg-pill">{{ $location }}</span>@endif</div>
                    <div class="pg-media" style="margin:16px 0"><div class="pg-media-overlay"><span class="pg-eyebrow">{{ strtoupper($kind) }}</span><h3 style="margin:.35rem 0 0">{{ $org->name }}</h3></div></div>
                    <p class="muted">{{ data_get($org->settings,'marketplace_summary') ?: 'Visit this community’s Private Gather site for events, membership information and community details.' }}</p>
                    @if($org->primaryDomain)
                        <div class="pg-actions"><a class="button button-primary" href="https://{{ $org->primaryDomain->domain }}">Visit community</a><span class="pg-pill">{{ $org->primaryDomain->domain }}</span></div>
                    @else
                        <span class="pg-pill pg-pill-warn">Hosted site being prepared</span>
                    @endif
                </article>
            @empty
                <div class="pg-empty"><h2>No communities are publicly listed yet</h2><p>Organizations can operate privately on Private Gather without appearing in the central directory.</p></div>
            @endforelse
        </div>
        <div style="margin-top:30px">{{ $organizations->links() }}</div>
    </div>
</section>
@endsection
