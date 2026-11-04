@extends('layouts.app')
@section('title','Attendees')
@section('content')
<section class="container section">
    <div class="eyebrow">ATTENDEES</div>
    <div class="panel-head">
        <h1>{{$event->title}}</h1>
        <div class="row-actions">
            <a class="button button-ghost" href="{{route('tenant.events.waitlist.index',$event)}}">Waitlist</a>
            <a class="button button-ghost" href="{{route('tenant.checkin.index',$event)}}">Door Check-In</a>
        </div>
    </div>
    <div class="panel">
        @foreach($rsvps as $rsvp)
            <div class="list-row">
                <div><strong>{{$rsvp->user->display_name?:$rsvp->user->name}}</strong><small>{{$rsvp->guest_count}} guest(s) · {{$rsvp->status}}</small></div>
                <form method="post" action="{{route('tenant.events.rsvp-status',[$event,$rsvp])}}">@csrf @method('patch')
                    <select name="status" onchange="this.form.submit()">@foreach(['pending','approved','rejected','cancelled'] as $x)<option @selected($rsvp->status===$x)>{{$x}}</option>@endforeach</select>
                </form>
            </div>
        @endforeach
    </div>
    {{$rsvps->links()}}
</section>
@endsection