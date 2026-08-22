@extends('layouts.app')
@section('title','Matches · '.$tenant->name)
@section('content')
<link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-network.css') }}">
<section class="container section pg-network-shell">
    <header class="pg-network-hero">
        <div><span class="eyebrow">PRIVATE MUTUAL MATCHING</span><h1>Interest stays private until it’s mutual.</h1><p>Like discoverable members privately. Private Gather does not reveal one-way interest; a match appears only after both members independently like each other.</p></div>
        <div class="row-actions"><a class="button button-ghost" href="{{ route('network.index') }}">Member Network</a><a class="button button-primary" href="{{ route('community.index') }}">Community</a></div>
    </header>

    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif

    <section class="pg-network-section">
        <div class="pg-network-section-head"><div><span class="eyebrow">YOUR MATCHES</span><h2>Mutual connections</h2><p>These members have independently liked you back. One-way likes are never shown here.</p></div><div class="pg-network-counter"><strong>{{ $matches->count() }}</strong><span>Matches</span></div></div>
        <div class="pg-member-grid">
            @forelse($matches as $member)
                <article class="panel pg-member-card">
                    <div class="pg-member-avatar">{{ strtoupper(substr($member->display_name ?: $member->name,0,2)) }}</div>
                    <div class="pg-member-copy"><strong>{{ $member->display_name ?: $member->name }}</strong><small>{{ $member->profile?->city ?: 'Location private' }}@if($member->profile?->region), {{ $member->profile->region }}@endif</small>@if($member->profile?->headline)<p>{{ $member->profile->headline }}</p>@endif</div>
                    <div class="pg-member-action"><span class="status-pill status-ok">Mutual Match</span><a class="btn" href="{{ route('messages.index') }}">Messages</a><form method="post" action="{{ route('matches.likes.destroy',$member) }}">@csrf @method('DELETE')<button class="btn">Remove Like</button></form></div>
                </article>
            @empty<div class="panel"><p class="muted">No mutual matches yet. Likes remain private unless interest becomes mutual.</p></div>@endforelse
        </div>
    </section>

    <section class="pg-network-section">
        <div class="pg-network-section-head"><div><span class="eyebrow">DISCOVER</span><h2>People you may like</h2><p>Only members who have chosen to be discoverable appear here.</p></div></div>
        <div class="pg-member-grid">
            @forelse($candidates as $member)
                @php($liked=in_array((int)$member->id,$likedIds,true))
                @php($matched=in_array((int)$member->id,$matchedIds,true))
                <article class="panel pg-member-card">
                    <div class="pg-member-avatar">{{ strtoupper(substr($member->display_name ?: $member->name,0,2)) }}</div>
                    <div class="pg-member-copy"><strong>{{ $member->display_name ?: $member->name }}</strong><small>{{ $member->profile?->city ?: 'Location private' }}@if($member->profile?->region), {{ $member->profile->region }}@endif @if($member->last_login_at) · active {{ $member->last_login_at->diffForHumans() }}@endif</small>@if($member->profile?->headline)<p>{{ $member->profile->headline }}</p>@endif @if(!empty($member->profile?->interests))<p class="muted">{{ implode(' · ',array_slice($member->profile->interests,0,4)) }}</p>@endif</div>
                    <div class="pg-member-action">
                        @if($matched)
                            <span class="status-pill status-ok">Mutual Match</span>
                        @elseif($liked)
                            <span class="status-pill status-pending">Liked privately</span>
                        @else
                            <form method="post" action="{{ route('matches.likes.store',$member) }}">@csrf<button class="button button-primary">Like Privately</button></form>
                        @endif
                        @if($liked)<form method="post" action="{{ route('matches.likes.destroy',$member) }}">@csrf @method('DELETE')<button class="btn">Undo</button></form>@endif
                    </div>
                </article>
            @empty<div class="panel"><p class="muted">No discoverable members are available right now.</p></div>@endforelse
        </div>
    </section>
</section>
@endsection
