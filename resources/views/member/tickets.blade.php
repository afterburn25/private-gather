@extends('layouts.app')
@section('title','My Tickets')
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">MEMBER WALLET</span><h1>My Tickets</h1><p>Keep admission credentials private. Unused valid tickets can be offered to another eligible Private Gather account; ownership changes only after the recipient accepts.</p></div></section>
<section class="pg-page"><div class="pg-shell">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if(session('ticket_transfer_link'))<div class="pg-card"><strong>Private transfer link</strong><p class="muted">If the recipient did not receive the in-app notification, send this link through a trusted channel. It expires automatically.</p><code style="word-break:break-all">{{ session('ticket_transfer_link') }}</code></div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="pg-grid pg-grid-2">
@forelse($tickets as $ticket)
@php($event=$ticket->orderItem?->order?->event)
@php($type=$ticket->orderItem?->ticketType)
@php($pending=$pendingTransfers->get($ticket->id))
<article class="pg-card form-stack">
<div class="row-between"><div><span class="pg-pill">{{ ucfirst($ticket->status) }}</span>@if($ticket->checked_in_at)<span class="pg-pill">Checked in</span>@endif</div><small>{{ $type?->name ?: 'Admission ticket' }}</small></div>
<h2>{{ $event?->title ?: 'Event unavailable' }}</h2>
@if($event?->starts_at)<p class="muted">{{ $event->starts_at->format('M j, Y · g:i A') }}@if($event->city) · {{ $event->city }}@endif</p>@endif
<div><small class="muted">Ticket ID</small><br><code>{{ $ticket->public_id }}</code></div>
<div><small class="muted">QR credential</small><br><code style="word-break:break-all">{{ $ticket->qr_token }}</code></div>
@if($ticket->checked_in_at)<div class="success">Checked in {{ $ticket->checked_in_at->format('M j, Y · g:i A') }}. Used tickets cannot be transferred.</div>
@elseif($ticket->status!=='valid')<div class="pg-privacy-note">Only unused valid tickets can be transferred.</div>
@elseif($pending)
<div class="pg-privacy-note"><strong>Transfer pending.</strong> Ownership remains yours until the recipient accepts. The offer expires {{ \Carbon\Carbon::parse($pending->expires_at)->format('M j, Y · g:i A') }}.</div>
<form method="post" action="{{ route('member.ticket-transfer.revoke',$pending->id) }}">@csrf @method('DELETE')<button class="button button-ghost">Revoke pending transfer</button></form>
@else
<details><summary><strong>Transfer this ticket</strong></summary><form method="post" action="{{ route('member.tickets.transfer',$ticket->id) }}" class="form-stack" style="margin-top:14px">@csrf
<label>Recipient Private Gather email<input type="email" name="email" required maxlength="255" autocomplete="email"></label>
<label>Private note / reason <span class="muted">optional</span><input name="reason" maxlength="500"></label>
<div class="pg-privacy-note">The recipient must still satisfy this event’s membership, approval and profile eligibility rules. When accepted, your current QR credential is permanently rotated and will no longer admit anyone.</div>
<button class="button button-primary">Offer ticket transfer</button>
</form></details>
@endif
</article>
@empty<div class="pg-card"><h2>No tickets yet</h2><p class="muted">Paid or complimentary admission tickets will appear here after they are issued.</p></div>@endforelse
</div>
{{ $tickets->links() }}
</div></section>
@endsection
