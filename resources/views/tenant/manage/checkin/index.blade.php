@extends('layouts.app')
@section('title', 'Door Mode · '.$event->title)
@section('content')
<section class="container section">
    <div class="panel-head"><div><div class="eyebrow">DOOR MODE</div><h1>{{ $event->title }}</h1><p class="muted">Admission, verification and club recognition at a glance. Private-only badges are never exposed here.</p></div><a class="button button-ghost" href="{{ route('tenant.events.edit', $event) }}">Event Manager</a></div>

    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <div class="stats"><div class="stat"><strong>{{ $checkedIn }}</strong><span>Checked In</span></div><div class="stat"><strong>{{ $approved }}</strong><span>Approved Guests</span></div><div class="stat"><strong>{{ $event->capacity ?? '∞' }}</strong><span>Capacity</span></div><div class="stat"><strong>{{ $remaining ?? '∞' }}</strong><span>Space Remaining</span></div></div>

    <div class="management-grid">
        <section class="panel"><h2>Scan / enter ticket</h2><form method="post" action="{{ route('tenant.checkin.ticket', $event) }}" class="form-stack">@csrf<input name="qr_token" placeholder="Ticket QR token" autocomplete="off" required><button class="button button-primary">Check In Ticket</button></form></section>
        <section class="panel"><h2>Named walk-in</h2><p class="muted">Use only when door policy allows an authorized walk-in without an existing member/ticket entitlement.</p><form method="post" action="{{ route('tenant.checkin.manual', $event) }}" class="form-stack">@csrf<input name="guest_name" placeholder="Guest / party name" required><input name="guest_count" type="number" min="1" max="20" value="1" required><button>Check In Walk-In</button></form></section>
    </div>

    <section class="panel"><div class="panel-head"><div><h2>Approved attendees</h2><p class="muted">Search by member name, display name or email.</p></div><form method="get" class="form-inline"><input name="q" value="{{ $search }}" placeholder="Search approved attendees"><button>Search</button></form></div>
        @forelse($attendees as $rsvp)
            @php($user=$rsvp->user)
            @if($user)
            <div class="list-row"><div><strong>{{ $user->display_name ?: $user->name }}</strong><small>{{ ucfirst($user->profile?->profile_type ?: 'member') }} profile · {{ $user->email }} · approved for {{ $rsvp->guest_count }} · {{ (int)($checkedByUser[$user->id] ?? 0) }} already checked in @if($activeMembership[$user->id] ?? false)· Active club member@endif</small><div class="row-actions">@foreach($doorBadges[$user->id] ?? [] as $assignment)<span class="pill">{{ $assignment->badge->isGlobal() ? 'PG ' : '' }}{{ $assignment->badge->icon }} {{ $assignment->badge->name }}</span>@endforeach</div></div><form method="post" action="{{ route('tenant.checkin.manual', $event) }}" class="form-inline">@csrf<input type="hidden" name="user_id" value="{{ $user->id }}"><input name="guest_count" type="number" min="1" max="{{ max(1, $rsvp->guest_count) }}" value="1"><button class="button button-primary">Check In</button></form></div>
            @endif
        @empty<p>No approved attendees match this search.</p>@endforelse
        {{ $attendees->links() }}
    </section>

    <section class="panel"><h2>Recent check-ins</h2>@forelse($checkins as $checkin)<div class="list-row"><div><strong>{{ $checkin->user?->display_name ?: $checkin->user?->name ?: data_get($checkin->metadata, 'guest_name', 'Walk-in guest') }}</strong><small>{{ strtoupper($checkin->method) }} · {{ $checkin->guest_count }} admitted · {{ $checkin->checked_in_at?->format('g:i A') }}</small></div></div>@empty<p>No check-ins yet.</p>@endforelse{{ $checkins->links() }}</section>
</section>
@endsection
