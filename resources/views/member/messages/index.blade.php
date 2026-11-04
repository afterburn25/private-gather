@extends('layouts.app')
@section('title','Messages')
@section('content')
<section class="shell section">
<div class="panel-head"><div><div class="eyebrow">PRIVATE MESSAGING</div><h1>{{$tenant->name}} Messages</h1><p class="muted">Messages and group chats stay inside this Private Gather community. Members from other hosted sites cannot be selected or added.</p></div><div class="row-actions"><a class="btn" href="{{route('community.index')}}">Community Wall</a><a class="btn" href="{{route('community.chat')}}">Live Chat</a></div></div>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
<div class="dashboard-grid">
<form class="panel form-stack" method="post" action="{{route('messages.start')}}">
@csrf
<h2>New conversation</h2>
@if($members->isEmpty())
<p class="muted">There are no other active members in this community yet.</p>
@else
<label>Members <small>Select one for a direct message or several for a group chat.</small></label>
<div style="max-height:260px;overflow:auto" class="panel">
@foreach($members as $member)
<label style="display:flex;gap:10px;align-items:center;margin:8px 0"><input type="checkbox" name="recipient_ids[]" value="{{$member->id}}"> <span>{{$member->display_name ?: $member->name}}</span></label>
@endforeach
</div>
<label>Group subject <small>Optional; used when several members are selected.</small><input name="subject" maxlength="160" placeholder="Weekend planning"></label>
<label>Message<textarea name="body" rows="4" maxlength="10000" placeholder="Write a private message" required></textarea></label>
<button class="button button-primary">Start conversation</button>
@endif
</form>
<div class="panel"><h2>Your conversations</h2>
@forelse($conversations as $conversation)
@php($others=$conversation->participants->where('id','!=',auth()->id()))
<div class="list-row"><div><strong>{{$conversation->subject ?: $others->map(fn($u)=>$u->display_name?:$u->name)->join(', ') ?: 'Conversation'}}</strong><small>{{$conversation->messages->first()?->body ?: 'No messages yet'}}</small></div><a class="btn" href="{{route('messages.show',$conversation)}}">Open</a></div>
@empty<p class="muted">No conversations yet.</p>@endforelse
</div>
</div>
</section>
@endsection
