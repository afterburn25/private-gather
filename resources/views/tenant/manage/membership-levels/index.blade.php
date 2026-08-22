@extends('layouts.app')

@section('title', 'Memberships · '.$tenant->name)

@section('content')
<section class="container section">
    <div class="panel-head">
        <div>
            <div class="eyebrow">MEMBERSHIP OPERATIONS</div>
            <h1>Membership Levels & Renewals</h1>
            <p class="muted">Manage lifestyle-club membership tiers separately from staff roles. Prices are stored now; online recurring billing can be connected to the payment layer without changing this membership model.</p>
        </div>
        <a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Back to Control Center</a>
    </div>

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

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
                    @if($level->event_discount_percent)<small>{{ $level->event_discount_percent }}% configured event discount</small>@endif
                    @if($level->guest_limit)<small>Up to {{ $level->guest_limit }} included guest(s) where event policy allows</small>@endif
                </div>
                <div class="row-actions">
                    @unless($level->is_default)
                        <form method="post" action="{{ route('tenant.membership-levels.default', $level) }}">@csrf<button>Make Default</button></form>
                    @endunless
                    <form method="post" action="{{ route('tenant.membership-levels.update', $level) }}">@csrf @method('patch')<input type="hidden" name="is_active" value="{{ $level->is_active ? 0 : 1 }}"><button>{{ $level->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                </div>
            </div>
        @empty
            <p>No membership levels configured.</p>
        @endforelse
    </section>

    <section class="panel">
        <h2>Assign or change a member level</h2>
        <p class="muted">This is staff-controlled membership activation. Assigning a level does not change owner/admin/staff roles.</p>
        <form method="post" action="{{ route('tenant.memberships.assign') }}" class="form-inline">
            @csrf
            <input type="email" name="email" placeholder="member@example.com" required>
            <select name="membership_level_id" required>
                @foreach($levels->where('is_active', true) as $level)
                    <option value="{{ $level->id }}">{{ $level->name }} · {{ $level->priceLabel() }} · {{ ucfirst($level->billing_interval) }}</option>
                @endforeach
            </select>
            <label class="check"><input type="checkbox" name="auto_renew" value="1"> Auto-renew flag</label>
            <input name="notes" placeholder="Internal note (optional)">
            <button class="button button-primary">Activate Level</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h2>Current member terms</h2><p class="muted">Renewals extend from the existing future end date, preventing members from losing paid time.</p></div>
            <form method="get" class="form-inline"><input name="q" value="{{ request('q') }}" placeholder="Search member"><button>Search</button></form>
        </div>

        @forelse($terms as $term)
            <div class="list-row">
                <div>
                    <strong>{{ $term->user->display_name ?: $term->user->name }} · {{ $term->level->name }}</strong>
                    <small>{{ ucfirst($term->status) }} · {{ $term->user->email }} @if($term->expires_at)· ends {{ $term->expires_at->format('M j, Y') }}@else · no expiration@endif @if($term->cancel_at_period_end)· cancellation scheduled@endif</small>
                    <small>{{ ucfirst($term->user->profile?->profile_type ?: 'member') }} profile · source {{ $term->source }}</small>
                </div>
                <div class="row-actions">
                    <form method="post" action="{{ route('tenant.memberships.renew', $term) }}">@csrf<button>Renew / Extend</button></form>
                    @if($term->isCurrentlyActive())
                        <form method="post" action="{{ route('tenant.memberships.cancel', $term) }}" class="form-inline">@csrf<input type="hidden" name="when" value="period_end"><input name="reason" placeholder="Reason (optional)"><button>End at Period</button></form>
                        <form method="post" action="{{ route('tenant.memberships.cancel', $term) }}" class="form-inline" onsubmit="return confirm('Cancel this membership now?')">@csrf<input type="hidden" name="when" value="now"><input name="reason" placeholder="Reason (optional)"><button class="danger">Cancel Now</button></form>
                    @endif
                </div>
            </div>
        @empty
            <p>No structured membership terms yet. Existing legacy members remain active until a level is assigned.</p>
        @endforelse
        {{ $terms->links() }}
    </section>
</section>
@endsection
