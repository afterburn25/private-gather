@extends('layouts.app')
@section('title', 'Groups')
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">MY GROUPS</span>
        <h1>Private groups across your communities.</h1>
        <p>Only groups inside communities where you have an active membership appear here. Open a group in its club context to read, join, post or manage membership.</p>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell">
        <div class="pg-grid pg-grid-3" style="margin-bottom:28px">
            <div class="pg-stat"><span>Communities</span><strong>{{ $communityCount }}</strong></div>
            <div class="pg-stat"><span>Available groups</span><strong>{{ $groups->count() }}</strong></div>
            <div class="pg-stat"><span>Joined / pending</span><strong>{{ $groups->filter(fn($group) => in_array($group->member_group_status, ['active','pending'], true))->count() }}</strong></div>
        </div>

        <div class="pg-section-head">
            <div>
                <span class="pg-eyebrow">COMMUNITY GROUPS</span>
                <h2>Your eligible group spaces</h2>
                <p>Group visibility stays bounded by your active community memberships.</p>
            </div>
        </div>

        <div class="pg-grid pg-grid-3">
            @forelse($groups as $group)
                <article class="pg-card pg-card-interactive">
                    <div class="row-between">
                        <span class="pg-pill">{{ ucfirst($group->visibility) }}</span>
                        @if($group->member_group_status)
                            <span class="pg-pill">{{ ucfirst($group->member_group_status) }}</span>
                        @endif
                    </div>
                    <h3 style="margin-top:14px">{{ $group->name }}</h3>
                    <p class="muted">{{ $group->tenant?->name }}</p>
                    <p>{{ $group->description ?: 'A private community group on Private Gather.' }}</p>
                    <div class="pg-trust-row">
                        <span class="pg-pill">{{ ucfirst(str_replace('_',' ', $group->join_policy)) }}</span>
                        <span class="pg-pill">{{ $group->activeMemberCount() }} members</span>
                    </div>
                    <div class="pg-actions" style="margin-top:18px">
                        @if($group->member_open_url)
                            <a class="button button-primary" href="{{ $group->member_open_url }}">Open group</a>
                        @else
                            <span class="muted">This community does not yet have a usable site domain.</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pg-empty">
                    <h3>No groups available yet</h3>
                    <p>Join a club or organization first. Groups from your active communities will appear here automatically.</p>
                    <a class="button button-primary" href="{{ route('site.organizations') }}">Browse clubs</a>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
