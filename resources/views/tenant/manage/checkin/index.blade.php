@extends('layouts.app')
@section('title','Check-In')
@section('content')
<div class="container narrow"><h1>Check-In · {{ $event->title }}</h1>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
<div class="grid two">
<form method="post" action="{{ route('tenant.checkin.ticket',$event) }}" class="panel">@csrf<h2>Scan / enter ticket</h2><input name="qr_token" placeholder="Ticket QR token" required><button>Check In Ticket</button></form>
<form method="post" action="{{ route('tenant.checkin.manual',$event) }}" class="panel">@csrf<h2>Manual check-in</h2><input name="user_id" type="number" placeholder="Member ID (optional)"><input name="guest_count" type="number" min="1" value="1"><button>Check In</button></form>
</div><h2>Recent check-ins</h2><div class="panel">@forelse($checkins as $c)<div>#{{$c->id}} · {{$c->method}} · {{$c->guest_count}} guest(s) · {{$c->checked_in_at}}</div>@empty<p>No check-ins yet.</p>@endforelse</div></div>
@endsection