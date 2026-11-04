@extends('layouts.app')
@section('title','Messages — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">PRIVATE MESSAGING</span><h1>Conversations inside {{ $tenant->name }}.</h1><p>Direct and group conversations stay inside this Private Gather community. Members from other hosted organizations cannot be selected or added.</p><div class="pg-actions" style="margin-top:22px"><a class="button button-ghost" href="{{ route('community.index') }}">Community wall</a><a class="button button-ghost" href="{{ route('community.groups.index') }}">Groups</a><a class="button button-ghost" href="{{ route('community.chat') }}">Live chat</a></div></div></section>
<section class="pg-page"><div class="pg-shell pg-grid" style="grid-template-columns:minmax(300px,.75fr) minmax(0,1.25fr);align-items:start">
<form class="pg-card form-stack" method="post" action="{{ route('messages.start') }}">@csrf<span class="pg-eyebrow">NEW CONVERSATION</span><h2>Start privately</h2>
@if($members->isEmpty())<div class="pg-empty">There are no other active members in this community yet.</div>@else
<label>Recipients <small>Select one for a direct message or several for a group conversation.</small></label><div class="pg-card" style="max-height:300px;overflow:auto;background:rgba(255,255,255,.02)!important">@foreach($members as $member)<label class="row-actions" style="margin:9px 0"><input type="checkbox" name="recipient_ids[]" value="{{ $member->id }}"><span>{{ $member->display_name ?: $member->name }}</span></label>@endforeach</div>
<label>Group subject <small>Optional when several members are selected.</small><input name="subject" maxlength="160" placeholder="Weekend planning"></label><label>Message<textarea name="body" rows="5" maxlength="10000" placeholder="Write a private message" required></textarea></label><button class="button button-primary">Start conversation</button>@endif
<div class="pg-privacy-note">Recipient choices are limited to active members of this community.</div></form>
<section class="pg-card"><div class="pg-section-head"><div><span class="pg-eyebrow">INBOX</span><h2>Your conversations</h2><p>Open a thread to continue in real time.</p></div></div><div class="pg-grid" style="gap:10px">
@forelse($conversations as $conversation) @php($others=$conversation->participants->where('id','!=',auth()->id())) <a class="pg-card pg-card-interactive" style="background:rgba(255,255,255,.025)!important" href="{{ route('messages.show',$conversation) }}"><div class="row-between"><div><strong>{{ $conversation->subject ?: $others->map(fn($u)=>$u->display_name?:$u->name)->join(', ') ?: 'Conversation' }}</strong><p class="muted" style="margin:.35rem 0 0">{{ \Illuminate\Support\Str::limit($conversation->messages->first()?->body ?: 'No messages yet',110) }}</p></div><span class="pg-pill">Open →</span></div></a>
@empty<div class="pg-empty"><h3>No conversations yet</h3><p>Start with an active member of {{ $tenant->name }}.</p></div>@endforelse
</div></section>
</div></section>
@endsection
