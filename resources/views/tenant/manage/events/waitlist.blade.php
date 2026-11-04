@extends('layouts.app')
@section('title','Event Waitlist')
@section('content')
<section class="container section">
    <div class="eyebrow">EVENT WAITLIST</div>
    <div class="panel-head">
        <div>
            <h1>{{$event->title}}</h1>
            <p class="muted">Review the queue and promote parties when capacity is available.</p>
        </div>
        <a class="button button-ghost" href="{{route('tenant.events.attendees',$event)}}">Back to Attendees</a>
    </div>

    @if(session('status'))<div class="notice">{{session('status')}}</div>@endif
    @if($errors->any())<div class="notice danger">{{$errors->first()}}</div>@endif

    <div class="stats">
        <div class="stat"><strong>{{$approvedSeats}}</strong><span>Approved seats</span></div>
        <div class="stat"><strong>{{$event->capacity===null?'Unlimited':$event->capacity}}</strong><span>Capacity</span></div>
        <div class="stat"><strong>{{$remainingSeats===null?'Unlimited':$remainingSeats}}</strong><span>Seats available</span></div>
        <div class="stat"><strong>{{$waitlist->total()}}</strong><span>Waitlisted parties</span></div>
    </div>

    <div class="panel">
        @forelse($waitlist as $entry)
            @php($fits=$remainingSeats===null || $entry->guest_count <= $remainingSeats)
            <div class="list-row">
                <div>
                    <strong>#{{$entry->position}} · {{$entry->display_name?:$entry->name}}</strong>
                    <small>{{$entry->email}} · {{$entry->guest_count}} guest(s) · account {{$entry->user_status}}</small>
                </div>
                <form method="post" action="{{route('tenant.events.waitlist.promote',[$event,$entry->id])}}">@csrf @method('patch')
                    <button type="submit" @disabled(!$fits || $entry->user_status!=='active')>{{$fits?'Promote':'Needs more capacity'}}</button>
                </form>
            </div>
        @empty
            <p class="muted">No one is currently waiting for this event.</p>
        @endforelse
    </div>
    {{$waitlist->links()}}
</section>
@endsection