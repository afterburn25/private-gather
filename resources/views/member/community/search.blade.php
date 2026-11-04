@extends('layouts.app')
@section('title',$scope==='network' ? 'Search Private Gather Community' : 'Search '.$tenant->name.' Community')
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">{{ $scope==='network' ? 'PRIVATE GATHER COMMUNITY' : strtoupper($tenant->name).' · COMMUNITY' }}</span>
<h1>Search community</h1>
<p>Search conversations and groups that your account is already allowed to see. Private group membership and member blocks still apply.</p>
<form method="get" action="{{ $scope==='network' ? route('network-community.search') : route('community.search') }}" class="form-inline" style="margin-top:20px"><label class="pg-sr-only" for="community-q">Search community</label><input id="community-q" name="q" maxlength="100" value="{{ $queryText }}" placeholder="Search posts and groups" required><button class="button button-primary">Search</button></form>
<div class="pg-actions" style="margin-top:16px"><a class="button button-ghost" href="{{ $scope==='network' ? route('network-community.index') : route('community.index') }}">Back to community</a></div>
</div></section>
<section class="pg-page"><div class="pg-shell">
@if(mb_strlen($queryText)<2)<div class="pg-card"><h2>Enter at least two characters</h2><p class="muted">Search stays inside your current community visibility boundary.</p></div>@else
<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">CONVERSATIONS</span><h2>Posts matching “{{ $queryText }}”</h2></div></div>
<div class="pg-grid">@forelse($posts as $post)<article class="pg-card"><div class="row-between"><div><strong>{{ $post->user?->display_name ?: $post->user?->name ?: 'Member' }}</strong><small style="display:block" class="muted">{{ $post->created_at?->diffForHumans() }}@if($post->group) · {{ $post->group->name }}@endif @if($post->event) · {{ $post->event->title }}@endif</small></div>@if($post->is_pinned)<span class="pg-pill">Pinned</span>@endif</div><p>{{ \Illuminate\Support\Str::limit($post->body,600) }}</p>@if($post->group)<a class="text-link" href="{{ $scope==='network' ? route('network-community.groups.show',$post->group) : route('community.groups.show',$post->group) }}">Open group →</a>@elseif($post->event)<a class="text-link" href="{{ $scope==='network' ? route('network-community.events.show',$post->event) : route('community.events.show',$post->event) }}">Open event discussion →</a>@else<a class="text-link" href="{{ $scope==='network' ? route('network-community.index') : route('community.index') }}">Open feed →</a>@endif</article>@empty<div class="pg-card"><h3>No visible posts matched</h3><p class="muted">Hidden groups, blocked accounts and removed posts are never returned by search.</p></div>@endforelse</div>
@if($posts->hasPages())<div style="margin-top:24px">{{ $posts->links() }}</div>@endif</section>
<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">GROUPS</span><h2>Matching groups</h2></div></div><div class="pg-grid pg-grid-3">@forelse($groups as $group)<a class="pg-card pg-card-interactive" href="{{ $scope==='network' ? route('network-community.groups.show',$group) : route('community.groups.show',$group) }}"><h3>{{ $group->name }}</h3><p class="muted">{{ \Illuminate\Support\Str::limit($group->description,180) }}</p></a>@empty<div class="pg-card"><p class="muted">No visible groups matched.</p></div>@endforelse</div></section>
@endif
</div></section>
@endsection
