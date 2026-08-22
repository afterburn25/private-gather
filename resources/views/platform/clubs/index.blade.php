@extends('layouts.app')
@section('title','Lifestyle Clubs Directory')
@section('meta_description','Find lifestyle clubs by country, state, region, city and club type. Browse publicly listed Private Gather clubs without exposing protected member or event details.')
@section('content')
<link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-discovery.css') }}">
<div class="pg-discovery-page">
<section class="page-hero pg-directory-hero"><div class="container"><span class="eyebrow">PRIVATE GATHER CLUB DIRECTORY</span><h1>Find clubs by location.</h1><p>Explore clubs that choose to be publicly listed on Private Gather. Search a city or region, compare club types and amenities, then visit the club's own site for membership and event details.</p></div></section>
<section class="section"><div class="container">
<form class="panel pg-directory-filters" method="get" action="{{ route('clubs.index') }}">
    <label>Search<input name="q" value="{{ request('q') }}" placeholder="Club name or keyword"></label>
    <label>Country<select name="country"><option value="">All countries</option>@foreach($filters['countries'] as $value)<option value="{{ $value }}" @selected(request('country')===$value)>{{ $value }}</option>@endforeach</select></label>
    <label>State / Region<select name="region"><option value="">All regions</option>@foreach($filters['regions'] as $value)<option value="{{ $value }}" @selected(request('region')===$value)>{{ $value }}</option>@endforeach</select></label>
    <label>City<select name="city"><option value="">All cities</option>@foreach($filters['cities'] as $value)<option value="{{ $value }}" @selected(request('city')===$value)>{{ $value }}</option>@endforeach</select></label>
    <label>Club type<select name="type"><option value="">All types</option>@foreach($filters['types'] as $value)<option value="{{ $value }}" @selected(request('type')===$value)>{{ ucwords(str_replace('_',' ',$value)) }}</option>@endforeach</select></label>
    <div class="pg-directory-filter-actions"><button class="button button-primary">Find Clubs</button><a class="button button-ghost" href="{{ route('clubs.index') }}">Clear</a></div>
</form>

<div class="section-heading" style="margin-top:28px"><div><span class="eyebrow">DIRECTORY RESULTS</span><h2>{{ number_format($clubs->total()) }} {{ \Illuminate\Support\Str::plural('club',$clubs->total()) }}</h2><p class="muted">@if(request('city'))Showing clubs in {{ request('city') }}.@elseif(request('region'))Showing clubs in {{ request('region') }}.@elseif(request('country'))Showing clubs in {{ request('country') }}.@else Browse all publicly listed clubs.@endif</p></div><a href="{{ route('site.events') }}">Browse events →</a></div>
<div class="pg-club-grid">
@forelse($clubs as $profile)
    @php($org=$profile->tenant)
    <article class="panel pg-club-card">
        <div class="pg-club-card-visual">
            @if($org?->branding?->logo_path)<img src="{{ \App\Support\MountUrl::to($org->branding->logo_path) }}" alt="{{ $profile->displayName() }} logo">@else<span class="pg-home-club-monogram">{{ strtoupper(substr($profile->displayName(),0,1)) }}</span>@endif
        </div>
        <div class="pg-club-card-head"><div><span class="pill">{{ ucwords(str_replace('_',' ',$profile->club_type)) }}</span>@if($profile->verified_at)<span class="pg-verified">✓ Verified</span>@endif</div>@if($profile->featured_until && $profile->featured_until->isFuture())<span class="pg-featured">Featured</span>@endif</div>
        <h2>{{ $profile->displayName() }}</h2>
        <p class="pg-club-location">{{ collect([$profile->city,$profile->region,$profile->country_code])->filter()->implode(', ') ?: 'Location not published' }}</p>
        @if($profile->short_description)<p>{{ \Illuminate\Support\Str::limit($profile->short_description,180) }}</p>@endif
        @if($profile->amenities)<div class="pg-amenities">@foreach(array_slice($profile->amenities,0,5) as $amenity)<span>{{ $amenity }}</span>@endforeach</div>@endif
        <div class="row-actions"><a class="button button-primary" href="{{ route('clubs.show',$org->slug) }}">View Club</a>@if($org->primaryDomain)<a class="button button-ghost" href="https://{{ $org->primaryDomain->domain }}" rel="noopener">Website</a>@endif</div>
    </article>
@empty
    <div class="empty-state"><h3>No clubs match those filters.</h3><p>Try a broader location or clear one of the filters.</p><a class="button button-ghost" href="{{ route('clubs.index') }}">Show All Clubs</a></div>
@endforelse
</div>
{{ $clubs->links() }}

@include('partials.affiliate-offers',['affiliateHeading'=>'Featured lifestyle travel & experiences','affiliatePlacement'=>'club_directory'])
</div></section>
</div>
@endsection
