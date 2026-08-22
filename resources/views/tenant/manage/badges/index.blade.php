@extends('layouts.app')
@section('title', 'Badges · '.$tenant->name)
@section('content')
<section class="container section">
    <div class="panel-head"><div><div class="eyebrow">MEMBER RECOGNITION</div><h1>{{ $tenant->name }} Badges</h1><p class="muted">Create club-specific membership, staff, event and achievement badges. Private Gather verification names are reserved for the global platform.</p></div><a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Back to Control Center</a></div>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <section class="panel">
        <h2>Create club or organization badge</h2>
        <form method="post" action="{{ route('tenant.badges.store') }}" class="form-stack">
            @csrf
            <div class="form-grid">
                <label>Name<input name="name" value="{{ old('name') }}" placeholder="VIP Member" required></label>
                <label>Slug<input name="slug" value="{{ old('slug') }}" placeholder="vip-member"></label>
                <label>Icon<input name="icon" value="{{ old('icon') }}" placeholder="★, VIP, ◆"></label>
                <label>Category<select name="category"><option>membership</option><option>reputation</option><option>events</option><option>community</option><option>staff</option><option>achievement</option><option>promotional</option><option>custom</option></select></label>
                <label>Visibility<select name="visibility"><option value="public">Public</option><option value="members">Logged-in members</option><option value="tenant_members" selected>Club members</option><option value="event_attendees">Event attendees</option><option value="staff">Staff only</option><option value="private">Member only</option></select></label>
                <label>Issuance<select name="issuance_type"><option value="manual">Manual</option><option value="membership">Follows active membership</option><option value="automatic">Event milestone</option></select></label>
                <label>Membership role<select name="membership_role"><option value="">Any active member</option><option>member</option><option>owner</option><option>admin</option><option>manager</option><option>staff</option><option>checkin</option></select></label>
                <label>Event count<input name="event_count" type="number" min="1" placeholder="10"></label>
                <label>Expires after days<input name="expires_after_days" type="number" min="1" max="3650"></label>
                <label>Badge color<input name="badge_color" value="#7d3b69" pattern="#[0-9A-Fa-f]{6}" required></label>
                <label>Text color<input name="text_color" value="#ffffff" pattern="#[0-9A-Fa-f]{6}" required></label>
                <label class="span2">Description<textarea name="description" rows="3"></textarea></label>
            </div>
            <button class="button button-primary">Create Badge</button>
        </form>
    </section>

    <section class="panel">
        <h2>Badge library</h2>
        @forelse($badges as $badge)
            <div class="list-row">
                <div>
                    <strong><span class="pill">{{ $badge->icon }} {{ $badge->name }}</span></strong>
                    <small>{{ ucfirst($badge->category) }} · {{ ucfirst($badge->issuance_type) }} · {{ $badge->active_assignments_count }} active · {{ $badge->visibility }}</small>
                    @if($badge->description)<small>{{ $badge->description }}</small>@endif
                    @if($badge->criteria)<small>Criteria: {{ json_encode($badge->criteria) }}</small>@endif
                    @if($badge->issuance_type === 'manual' && $badge->is_active)
                        <form method="post" action="{{ route('tenant.badges.assign', $badge) }}" class="form-inline">
                            @csrf
                            <input type="email" name="email" placeholder="active-member@example.com" required>
                            <input type="datetime-local" name="expires_at">
                            <button>Assign</button>
                        </form>
                    @endif
                </div>
                <form method="post" action="{{ route('tenant.badges.update', $badge) }}">
                    @csrf @method('patch')<input type="hidden" name="is_active" value="{{ $badge->is_active ? 0 : 1 }}"><button>{{ $badge->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            </div>
        @empty<p>No club badges yet.</p>@endforelse
    </section>

    <section class="panel">
        <h2>Active assignments</h2>
        @forelse($assignments as $assignment)
            <div class="list-row"><div><strong>{{ $assignment->badge->name }} → {{ $assignment->user->display_name ?: $assignment->user->name }}</strong><small>{{ $assignment->user->email }} · {{ $assignment->issued_at?->format('M j, Y') }}</small></div>@if($assignment->badge->issuance_type === 'manual')<form method="post" action="{{ route('tenant.badges.revoke', [$assignment->badge, $assignment]) }}" class="form-inline">@csrf @method('delete')<input name="reason" placeholder="Reason" required><button class="danger">Revoke</button></form>@else<span class="muted">System managed</span>@endif</div>
        @empty<p>No active badge assignments.</p>@endforelse
    </section>
</section>
@endsection
