@extends('layouts.app')
@section('title',$tenant ? 'Member Discovery — '.$tenant->name : 'Member Discovery — Private Gather')
@push('head')
<link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/product-completion.css') }}">
@endpush
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">{{ $tenant ? strtoupper($tenant->name).' · MEMBER DISCOVERY' : 'PRIVATE GATHER · MEMBER DISCOVERY' }}</span>
<h1>{{ $tenant ? 'Find members within this community.' : 'Discover members across Private Gather.' }}</h1>
<p>{{ $tenant ? 'Discovery stays tenant-scoped and applies both organization policy and each member’s own discoverability, profile visibility, location visibility and block choices.' : 'The network directory is available only to signed-in members and applies each person’s discoverability, profile visibility, location visibility, connections-only choices and blocks.' }}</p>
<div class="pg-actions">@if(!$tenant && Route::has('network-community.index'))<a class="button button-ghost" href="{{ route('network-community.index') }}">Community</a>@endif<a class="button button-ghost" href="{{ route('connections.index') }}">My connections</a><a class="button button-ghost" href="{{ route('privacy.index') }}">My privacy</a></div>
</div></section>

<section class="pg-page"><div class="pg-shell">
<div class="pc-two">
<main>
@if(in_array($directoryMode,['members','public','network'],true))
<form method="get" class="pg-card form-inline" role="search">
<label class="pg-sr-only" for="member-q">Search members</label>
<input id="member-q" name="q" value="{{ request('q') }}" placeholder="Display name or headline">
<label class="pg-sr-only" for="member-city">City</label>
<input id="member-city" name="city" value="{{ request('city') }}" placeholder="City">
<button class="button button-primary">Search</button>
</form>
@endif

<div class="pg-grid pg-grid-3" style="margin-top:16px">
@forelse($members as $member)
@php
$privacy=$privacyByUser->get($member->id);
$locationMode=$privacy?->location_visibility ?? 'city';
$locationLabel=match($locationMode){
    'hidden'=>'Location private',
    'region'=>$member->profile?->region ?: 'Region private',
    default=>$member->profile?->city ?: 'City private',
};
@endphp
<article class="pg-card">
<span class="pg-pill">{{ $member->profile?->profile_type ? ucfirst($member->profile->profile_type) : 'Member' }}</span>
<h3>{{ $member->display_name ?: $member->name }}</h3>
@if($member->profile?->headline)<p>{{ $member->profile->headline }}</p>@endif
<p class="muted">{{ $locationLabel }}</p>
<div class="pg-actions">
<form method="post" action="{{ route('connections.request',$member) }}">@csrf<button class="button button-primary">Connect</button></form>
<form method="post" action="{{ route('blocks.store',$member) }}">@csrf<button class="button button-ghost" data-confirm="Block this member?">Block</button></form>
</div>
</article>
@empty
<div class="pg-empty"><h3>No eligible profiles to show</h3><p>{{ $directoryBoundaryMessage }}</p></div>
@endforelse
</div>
{{ $members->links() }}
</main>

<aside class="pg-card">
<span class="pg-eyebrow">DISCOVERY BOUNDARY</span>
<h2>{{ $directoryMode === 'network' ? 'Private Gather network directory' : ucfirst($directoryMode).' directory' }}</h2>
<p class="muted">{{ $directoryBoundaryMessage }}</p>
<div class="pg-privacy-note">{{ $tenant ? 'A tenant-level directory setting can only reduce exposure. It never overrides a member’s private/connections-only profile setting, hidden location, non-discoverable profile or block list.' : 'The network directory never overrides a member’s private/connections-only profile setting, hidden location, non-discoverable profile or block list. It is not available anonymously.' }}</div>
@if($directoryMode==='verified')<div class="pg-privacy-note" style="margin-top:12px">Email verification is not treated as identity verification.</div>@endif
</aside>
</div>
</div></section>
@endsection
