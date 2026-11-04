@extends('layouts.app')
@section('title', 'Memberships · '.$tenant->name)
@section('content')
<section class="container section">
    <div class="panel-head">
        <div>
            <div class="eyebrow">MEMBERSHIP OPERATIONS</div>
            <h1>Membership Levels & Renewals</h1>
            <p class="muted">Manage membership tiers, grace periods, renewals and invitations separately from staff roles. Paid renewals require confirmed payment. Private Gather does not extend a paid membership merely because its auto-renew flag is enabled.</p>
        </div>
        <div class="row-actions"><a class="button button-ghost" href="{{ route('tenant.membership-invitations.index') }}">Membership invitations</a><a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Back to Control Center</a></div>
    </div>

    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <section class="panel">
        <h2>Create membership level</h2>
        <form method="post" action="{{ route('tenant.membership-levels.store') }}" class="form-stack">
            @csrf
            <div class="form-grid">
                <label>Name<input name="name" value="{{ old('name') }}" placeholder="VIP Membership" required></label>
                <label>Slug<input name="slug" value="{{ old('slug') }}" placeholder="vip-membership"></label>
                <label>Price<input name="price" type="number" step="0.01" min="0" value="{{ old('price', '0.00') }}" required></label>
                <label>Billing / term<select name="billing_interval" required><option value="none">No expiration</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annual">Annual</option><option value="lifetime">Lifetime</option><option value="custom">Custom days</option></select></label>
                <label>Custom days<input name="duration_days" type="number" min="1" max="3650" value="{{ old('duration_days') }}"></label>
                <label>Grace days<input name="grace_days" type="number" min="0" max="90" value="{{ old('grace_days',0) }}" required><small>Extra access after the billing/term boundary before expiration.</small></label>
                <label>Eligible profile<select name="profile_eligibility"><option value="any">Couples or individuals</option><option value="couple">Couple profiles only</option><option value="individual">Individual profiles only</option></select></label>
                <label>Included guests<input name="guest_limit" type="number" min="0" max="100" value="{{ old('guest_limit', 0) }}" required></label>
                <label>Event discount %<input name="event_discount_percent" type="number" min="0" max="100" value="{{ old('event_discount_percent', 0) }}" required></label>
                <label>Sort order<input name="sort_order" type="number" min="0" value="{{ old('sort_order', 100) }}"></label>
                <label class="span2">Description<textarea name="description" rows="3">{{ old('description') }}</textarea></label>
                <label class="span2">Benefits — one per line<textarea name="benefits" rows="4" placeholder="Member-only events&#10;Priority RSVP&#10;10% event discount">{{ old('benefits') }}</textarea></label>
            </div>
            <label class="check"><input type="checkbox" name="requires_approval" value="1" checked> Requires club/organization approval</label>
            <button class="button button-primary">Create Membership Level</button>
        </form>
    </section>

    <section class="panel">
        <h2>Membership level library</h2>
        @forelse($levels as $level)
            <div class="list-row">
                <div>
                    <strong>{{ $level->name }} @if($level->is_default)<span class="pill">Default</span>@endif</strong>
                    <small>{{ $level->priceLabel() }} · {{ ucfirst($level->billing_interval) }} · {{ $level->profile_eligibility === 'any' ? 'Couples & individuals' : ucfirst($level->profile_eligibility).' only' }} · {{ $level->terms_count }} assigned</small>
                    <small>{{ $level->grace_days ? $level->grace_days.' day grace period' : 'No grace period' }}@if($level->event_discount_percent) · {{ $level->event_discount_percent }}% event discount @endif @if($level->guest_limit) · {{ $level->guest_limit }} included guest(s) @endif</small>
                </div>
                <div class="row-actions">
                    <form method="post" action="{{ route('tenant.membership-levels.update', $level) }}" class="form-inline">@csrf @method('patch')<input name="grace_days" type="number" min="0" max="90" value="{{ $level->grace_days }}" style="width:92px"><button>Save Grace</button></form>
                    @unless($level->is_default)<form method="post" action="{{ route('tenant.membership-levels.default', $level) }}">@csrf<button>Make Default</button></form>@endunless
                    <form method="post" action="{{ route('tenant.membership-levels.update', $level) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $level->is_active ? 0 : 1 }}"><button>{{ $level->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                </div>
            </div>
        @empty<p>No membership levels configured.</p>@endforelse
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>Assign, change or invite</h2><p class="muted">Direct assignment is for existing accounts you are activating immediately. Invitations let the member explicitly accept from their own account.</p></div><a class="button button-primary" href="{{ route('tenant.membership-invitations.index') }}">Open Invitations</a></div>
        <form method="post" action="{{ route('tenant.memberships.assign') }}" class="form-inline">
            @csrf
            <input type="email" name="email" placeholder="member@example.com" required>
            <select name="membership_level_id" required>@foreach($levels->where('is_active', true) as $level)<option value="{{ $level->id }}">{{ $level->name }} · {{ $level->priceLabel() }} · {{ ucfirst($level->billing_interval) }}</option>@endforeach</select>
            <label class="check"><input type="checkbox" name="auto_renew" value="1"> Auto-renew flag</label>
            <input name="notes" placeholder="Internal note (optional)">
            <button class="button button-primary">Activate Level</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>Current member terms</h2><p class="muted">Period end is the billing/term boundary. Access end includes any configured grace period. Free auto-renewals may roll forward automatically. Paid renewals require confirmed payment.</p></div><form method="get" class="form-inline"><input name="q" value="{{ request('q') }}" placeholder="Search member"><button>Search</button></form></div>
        @forelse($terms as $term)
            <div class="list-row">
                <div>
                    <strong>{{ $term->user->display_name ?: $term->user->name }} · {{ $term->level->name }}</strong>
                    <small>{{ ucfirst($term->status) }} · {{ $term->user->email }} @if($term->current_period_ends_at)· period ends {{ $term->current_period_ends_at->format('M j, Y') }}@else · no period end@endif @if($term->expires_at && (!$term->current_period_ends_at || !$term->expires_at->equalTo($term->current_period_ends_at)))· access ends {{ $term->expires_at->format('M j, Y') }}@endif @if($term->cancel_at_period_end)· cancellation scheduled@endif</small>
                    <small>{{ ucfirst($term->user->profile?->profile_type ?: 'member') }} profile · source {{ $term->source }}@if($term->renewal_reminder_sent_at) · reminder sent {{ $term->renewal_reminder_sent_at->diffForHumans() }}@endif</small>
                </div>
                <div class="row-actions">
                    <form method="post" action="{{ route('tenant.memberships.renew', $term) }}">@csrf<button>Renew / Extend</button></form>
                    @if($term->isCurrentlyActive())
                        <form method="post" action="{{ route('tenant.memberships.cancel', $term) }}" class="form-inline">@csrf<input type="hidden" name="when" value="period_end"><input name="reason" placeholder="Reason (optional)"><button>End at Period</button></form>
                        <form method="post" action="{{ route('tenant.memberships.cancel', $term) }}" class="form-inline" onsubmit="return confirm('Cancel this membership now?')">@csrf<input type="hidden" name="when" value="now"><input name="reason" placeholder="Reason (optional)"><button class="danger">Cancel Now</button></form>
                    @endif
                </div>
            </div>
        @empty<p>No structured membership terms yet. Existing legacy members remain active until a level is assigned.</p>@endforelse
        {{ $terms->links() }}
    </section>
</section>
@endsection
