@extends('layouts.app')
@section('title','Community Groups — Private Gather')
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">PRIVATE GATHER COMMUNITY</span><h1>Community groups</h1><p>Member-created spaces for shared interests across the Private Gather network. Group content stays members-only and remains separate from every club or organization community.</p><div class="pg-actions" style="margin-top:22px"><a class="button button-ghost" href="{{ route('network-community.index') }}">Network feed</a><a class="button button-primary" href="#create-group">Create a group</a></div></div></section>
<section class="pg-page"><div class="pg-shell pg-grid" style="grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);align-items:start">
<div class="pg-grid">
@if(session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
@forelse($groups as $group)
<a class="pg-card pg-card-interactive" href="{{ route('network-community.groups.show',$group) }}"><div class="row-between"><div><span class="pg-eyebrow">{{ $group->join_policy === 'open' ? 'OPEN GROUP' : 'APPROVAL GROUP' }}</span><h2>{{ $group->name }}</h2></div>@php($state=$memberships[$group->id] ?? null)@if($state)<span class="badge">{{ ucfirst($state) }}</span>@endif</div><p class="muted">{{ $group->description ?: 'A Private Gather member community group.' }}</p><span class="text-link">Open group →</span></a>
@empty<div class="pg-empty"><h3>No network groups yet</h3><p>Create the first Private Gather community group.</p></div>@endforelse
{{ $groups->links() }}
</div>
<aside id="create-group" class="pg-card form-stack" style="position:sticky;top:140px"><span class="pg-eyebrow">CREATE GROUP</span><h2>Start a new circle</h2><form class="form-stack" method="post" action="{{ route('network-community.groups.store') }}">@csrf<label>Name<input name="name" maxlength="120" required></label><label>Description<textarea name="description" rows="5" maxlength="3000" placeholder="What is this group for?"></textarea></label><label>Join policy<select name="join_policy" required><option value="open">Open — members join immediately</option><option value="approval">Approval — owner/moderator approves</option></select></label><button class="button button-primary">Create community group</button></form><div class="pg-privacy-note">Creating a network group does not create a tenant, chapter, event, club membership or public listing.</div></aside>
</div></section>
@endsection
