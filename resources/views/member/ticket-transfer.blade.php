@extends('layouts.app')
@section('title','Accept Ticket Transfer')
@section('content')
@php($event=$ticket->orderItem?->order?->event)
@php($type=$ticket->orderItem?->ticketType)
<section class="pg-page"><div class="pg-shell" style="max-width:760px">
<div class="pg-card form-stack">
<span class="pg-eyebrow">SECURE TICKET TRANSFER</span>
<h1>{{ $event?->title ?: 'Event ticket' }}</h1>
<p>A member has offered you this {{ $type?->name ?: 'admission ticket' }}. Ownership does not change until you accept.</p>
@if($event?->starts_at)<p class="muted">{{ $event->starts_at->format('M j, Y · g:i A') }}@if($event->city) · {{ $event->city }}@endif</p>@endif
<div class="pg-privacy-note">Acceptance rechecks the event’s current membership, approval and profile eligibility rules. If accepted, Private Gather rotates the ticket’s QR credential so the sender’s prior credential can no longer be used.</div>
@if($transfer->reason)<div><strong>Sender note</strong><p>{{ $transfer->reason }}</p></div>@endif
<p class="muted">Offer expires {{ \Carbon\Carbon::parse($transfer->expires_at)->format('M j, Y · g:i A') }}.</p>
<form method="post" action="{{ route('member.ticket-transfer.accept',$token) }}">@csrf<button class="button button-primary">Accept ticket transfer</button></form>
<a class="button button-ghost" href="{{ route('member.tickets') }}">Return to my tickets</a>
</div>
</div></section>
@endsection
