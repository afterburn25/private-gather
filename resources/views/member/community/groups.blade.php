@extends('layouts.app')
@section('title','Groups — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">PRIVATE GROUPS</span><h1>Find your smaller circle.</h1><p>Groups live inside {{ $tenant->name }} and inherit its member-only privacy boundary. Invite-only groups stay hidden unless you belong to them.</p><div class="pg-actions" style="margin-top:22px"><a class="button button-ghost" href="{{ route('community.index') }}">Community wall</a><a class="button button-ghost" href="{{ route('community.chat') }}">Live chat</a></div></div></section>
<section class="pg-page"><div class="pg-shell">
<div class="pg-grid pg-grid-3">
@forelse($groups as $group)
<a class="pg-card pg-card-interactive" href="{{ route('community.groups.show',$group) }}"><div class="pg-event-meta"><span class="pg-pill">{{ $group->visibility === 'invite_only' ? 'Invite-only' : 'Members' }}</span><span class="pg-pill">{{ str_replace('_',' ',ucfirst($group->join_policy)) }}</span></div><h3 style="font-size:1.45rem;margin:.8rem 0 .35rem">{{ $group->name }}</h3><p class="muted">{{ $group->description ?: 'A private group inside this community.' }}</p><span class="pg-pill">{{ $group->active_members_count }} members</span></a>
@empty<div class="pg-empty"><h2>No groups yet</h2><p>Groups give members smaller spaces for shared interests, recurring connections and private discussion.</p></div>@endforelse
</div>
@if($canCreate)
<section class="pg-section"><form method="post" action="{{ route('community.groups.store') }}" class="pg-card form-stack">@csrf<span class="pg-eyebrow">COMMUNITY MANAGEMENT</span><h2>Create a group</h2><div class="form-grid"><label>Name<input name="name" maxlength="120" required></label><label>Visibility<select name="visibility"><option value="tenant">Visible to community members</option><option value="invite_only">Invite-only</option></select></label><label>Join policy<select name="join_policy"><option value="approval">Request approval</option><option value="open">Open to members</option><option value="invite_only">Invite-only</option></select></label><label class="row-actions" style="align-self:end"><input type="checkbox" name="allow_member_posts" value="1" checked> Members may post</label><label class="span2">Description<textarea name="description" rows="4" maxlength="4000"></textarea></label></div><button class="button button-primary">Create group</button></form></section>
@endif
</div></section>
@endsection
