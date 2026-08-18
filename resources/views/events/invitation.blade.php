@extends('layouts.app')
@section('title','Event Invitation')
@section('content')
<section class="auth-wrap"><div class="panel"><div class="eyebrow">PRIVATE INVITATION</div><h1>{{$invite->event->title}}</h1><p>You have been invited to this private event hosted by {{$invite->event->tenant->name}}.</p><div class="event-meta"><span>{{$invite->event->starts_at->format('M j, Y g:i A')}}</span><span>{{$invite->event->public_location_label?:$invite->event->city}}</span><span>Up to {{$invite->max_guests}} guest(s)</span></div>@if($invite->expires_at)<p><small>This invitation expires {{$invite->expires_at->format('M j, Y g:i A')}}.</small></p>@endif<form method="post" class="form-stack" action="{{route('event-invitations.accept',$invite->token)}}">@csrf<label>Number attending<select name="guest_count">@for($i=1;$i<=$invite->max_guests;$i++)<option value="{{$i}}">{{$i}}</option>@endfor</select></label><button class="btn primary">Accept Invitation</button></form></div></section>
@endsection
