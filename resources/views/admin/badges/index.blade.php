@extends('layouts.admin')
@section('title', 'Global Badges')
@section('content')
<div class="pg-admin-page-head">
    <div>
        <span class="pg-admin-kicker">Trust & recognition</span>
        <h1>Private Gather Global Badges</h1>
        <p>Global badges follow a member across clubs. Verification and platform-authority badges can only be created here.</p>
    </div>
</div>

@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif

<section class="pg-admin-card">
    <div class="pg-admin-card-head"><h2>Create global badge</h2></div>
    <form method="post" action="{{ route('admin.badges.store') }}" class="form-stack">
        @csrf
        <div class="grid two">
            <label>Name<input name="name" value="{{ old('name') }}" required></label>
            <label>Slug<input name="slug" value="{{ old('slug') }}" placeholder="identity-verified"></label>
            <label>Icon<input name="icon" value="{{ old('icon') }}" placeholder="✓, PG, ★"></label>
            <label>Category<select name="category"><option>verification</option><option>reputation</option><option>events</option><option>community</option><option>staff</option><option>achievement</option><option>promotional</option><option>custom</option></select></label>
            <label>Visibility<select name="visibility"><option value="public">Public</option><option value="members" selected>Logged-in members</option><option value="tenant_members">Same-club members</option><option value="event_attendees">Event attendees</option><option value="staff">Staff only</option><option value="private">Member only</option></select></label>
            <label>Issuance<select name="issuance_type"><option value="manual">Manual</option><option value="verification">Verification record</option><option value="automatic">Event milestone</option></select></label>
            <label>Verification type<input name="verification_type" value="{{ old('verification_type') }}" placeholder="identity"></label>
            <label>Event count<input name="event_count" type="number" min="1" value="{{ old('event_count') }}" placeholder="10"></label>
            <label>Expires after days<input name="expires_after_days" type="number" min="1" max="3650" value="{{ old('expires_after_days') }}"></label>
            <label>Badge color<input name="badge_color" value="{{ old('badge_color', '#7d3b69') }}" pattern="#[0-9A-Fa-f]{6}" required></label>
            <label>Text color<input name="text_color" value="{{ old('text_color', '#ffffff') }}" pattern="#[0-9A-Fa-f]{6}" required></label>
        </div>
        <label>Description<textarea name="description" rows="3">{{ old('description') }}</textarea></label>
        <label class="check"><input type="checkbox" name="is_system_reserved" value="1"> Reserve this identity as platform-controlled</label>
        <button class="button button-primary">Create Global Badge</button>
    </form>
</section>

<section class="pg-admin-card">
    <div class="pg-admin-card-head"><h2>Global badge library</h2></div>
    <div class="pg-admin-list">
        @forelse($badges as $badge)
            <div class="pg-admin-list-row">
                <div>
                    <strong><span class="pill">PG {{ $badge->icon }} {{ $badge->name }}</span></strong>
                    <small>{{ ucfirst($badge->category) }} · {{ ucfirst($badge->issuance_type) }} · {{ $badge->active_assignments_count }} active · {{ $badge->visibility }}</small>
                    @if($badge->description)<small>{{ $badge->description }}</small>@endif
                    @if($badge->criteria)<small>Criteria: {{ json_encode($badge->criteria) }}</small>@endif
                    @if($badge->issuance_type === 'manual' && $badge->is_active)
                        <form method="post" action="{{ route('admin.badges.assign', $badge) }}" class="form-inline">
                            @csrf
                            <input type="email" name="email" placeholder="member@example.com" required>
                            <input type="datetime-local" name="expires_at">
                            <button>Assign</button>
                        </form>
                    @endif
                </div>
                <form method="post" action="{{ route('admin.badges.update', $badge) }}">
                    @csrf @method('patch')
                    <input type="hidden" name="is_active" value="{{ $badge->is_active ? 0 : 1 }}">
                    <button>{{ $badge->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            </div>
        @empty
            <div class="muted">No global badges yet.</div>
        @endforelse
    </div>
</section>

<section class="pg-admin-card">
    <div class="pg-admin-card-head"><h2>Recent active assignments</h2></div>
    <div class="pg-admin-list">
        @forelse($assignments as $assignment)
            <div class="pg-admin-list-row">
                <div><strong>{{ $assignment->badge->name }} → {{ $assignment->user->display_name ?: $assignment->user->name }}</strong><small>{{ $assignment->user->email }} · issued {{ $assignment->issued_at?->format('M j, Y') }} @if($assignment->expires_at)· expires {{ $assignment->expires_at->format('M j, Y') }}@endif</small></div>
                @if($assignment->badge->issuance_type === 'manual')
                    <form method="post" action="{{ route('admin.badges.revoke', [$assignment->badge, $assignment]) }}" class="form-inline">
                        @csrf @method('delete')
                        <input name="reason" placeholder="Revocation reason" required>
                        <button class="danger">Revoke</button>
                    </form>
                @else
                    <span class="muted">System managed</span>
                @endif
            </div>
        @empty
            <div class="muted">No active global badge assignments.</div>
        @endforelse
    </div>
</section>
@endsection
