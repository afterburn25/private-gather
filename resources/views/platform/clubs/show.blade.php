@extends('layouts.app')
@section('title',$profile->displayName().' · Clubs')
@section('meta_description',trim(($profile->short_description ?: $profile->displayName().' on Private Gather').' '.collect([$profile->city,$profile->region])->filter()->implode(', ')))
@section('content')
<link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-discovery.css') }}">
<div class="pg-discovery-page">
<section class="page-hero pg-club-detail-hero"><div class="container">
    <div class="pg-club-detail-brand">
        <div class="pg-club-detail-logo">@if($tenant?->branding?->logo_path)<img src="{{ \App\Support\MountUrl::to($tenant->branding->logo_path) }}" alt="{{ $profile->displayName() }} logo">@else<span class="pg-home-club-monogram">{{ strtoupper(substr($profile->displayName(),0,1)) }}</span>@endif</div>
        <div><span class="eyebrow">PRIVATE GATHER CLUB DIRECTORY</span><h1>{{ $profile->displayName() }}</h1><p>{{ collect([$profile->city,$profile->region,$profile->country_code])->filter()->implode(', ') }} @if($profile->verified_at) · Verified listing @endif</p></div>
    </div>
    <div class="row-actions" style="margin-top:20px">@auth @if(!$membershipStatus)<form method="post" action="{{route('clubs.apply',$tenant)}}">@csrf<button class="button button-primary">Join / Apply to {{ $profile->displayName() }}</button></form>@else<span class="status-pill {{$membershipStatus==='active'?'status-ok':'status-pending'}}">Membership: {{ucfirst($membershipStatus)}}</span>@endif @else<a class="button button-primary" href="{{route('login')}}">Sign in to Join</a>@endauth @if($tenant->primaryDomain)<a class="button button-ghost" href="https://{{ $tenant->primaryDomain->domain }}" rel="noopener">Visit Club Website</a>@endif<a class="button button-ghost" href="{{ route('clubs.index') }}">Back to Clubs</a></div>
</div></section>
<section class="section"><div class="container pg-club-detail-grid">
    <main>
        <article class="panel pg-club-about"><div class="row-between"><span class="pill">{{ ucwords(str_replace('_',' ',$profile->club_type)) }}</span>@if($profile->featured_until && $profile->featured_until->isFuture())<span class="pg-featured">Featured club</span>@endif</div><h2>About {{ $profile->displayName() }}</h2><p>{{ $profile->short_description ?: 'This club has not added a public description yet.' }}</p>@if($profile->amenities)<h3>Amenities & features</h3><div class="pg-amenities">@foreach($profile->amenities as $amenity)<span>{{ $amenity }}</span>@endforeach</div>@endif @if($profile->contact_url)<p><a class="text-link" href="{{ $profile->contact_url }}" rel="noopener">Club information & contact →</a></p>@endif</article>

        <section class="pg-upcoming-club-events"><div class="section-heading"><div><span class="eyebrow">UPCOMING EVENTS</span><h2>What's happening here</h2><p class="muted">Only public events are shown in the directory. Protected details stay within the club's access rules.</p></div></div><div class="event-grid">@forelse($events as $event)<article class="event-card"><a href="{{ route('events.show',$event) }}"><div class="event-image">@if($event->cover_image_path)<img src="{{ \App\Support\MountUrl::to($event->cover_image_path) }}" alt="{{ $event->title }}">@endif<span>{{ $event->starts_at->format('M d') }}</span></div><div class="event-card-body"><span class="eyebrow">{{ strtoupper($event->public_location_label ?: $event->city ?: 'PRIVATE LOCATION') }}</span><h3>{{ $event->title }}</h3><p>{{ $event->starts_at->format('D, M j · g:i A') }}</p><span class="text-link">View event →</span></div></a></article>@empty<div class="empty-state"><p>No upcoming public events are currently listed.</p></div>@endforelse</div></section>
    </main>
    <aside><div class="panel pg-club-location-card"><span class="eyebrow">CLUB DETAILS</span><h3>{{ $profile->city ?: 'Location' }}</h3><p>{{ collect([$profile->region,$profile->country_code])->filter()->implode(', ') }}</p>@if($profile->postal_code)<small>{{ $profile->postal_code }}</small>@endif
@if($profile->verified_at)<div class="pg-privacy-line"><span>Listing status</span><b>Verified</b></div>@endif<div class="pg-privacy-line"><span>Club type</span><b>{{ ucwords(str_replace('_',' ',$profile->club_type)) }}</b></div></div></aside>
</div>
<div class="container">@include('partials.affiliate-offers',['affiliateHeading'=>'Sponsored experiences you may like','affiliatePlacement'=>'club_detail'])</div>
</section>
</div>
@endsection
