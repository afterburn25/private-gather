@extends('layouts.app')
@section('title','Member Operations — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">MEMBERSHIP OPERATIONS</span><h1>Bulk member operations</h1>
<p>Safely manage organization membership state without changing a person’s global Private Gather account.</p>
<div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.membership-levels.index') }}">Membership levels</a><a class="button button-ghost" href="{{ route('tenant.membership-invitations.index') }}">Invitations</a><a class="button button-ghost" href="{{ route('tenant.member-operations.export') }}">Export CSV</a></div>
</div></section>
<section class="pg-page"><div class="pg-shell pg-grid">
<div class="pg-grid pg-grid-2">
<form class="pg-card form-stack" method="post" action="{{ route('tenant.member-operations.import') }}" enctype="multipart/form-data">@csrf
<span class="pg-eyebrow">CSV IMPORT</span><h2>Assign existing accounts in bulk</h2>
<input type="file" name="csv" accept=".csv,text/csv" required>
<p class="muted">Required columns: <code>email,membership_level</code>. Optional: <code>auto_renew,notes</code>. Membership level may be its name or slug. Maximum 1,000 rows and 2 MB. Accounts are never created by import.</p>
<button class="button button-primary">Import membership CSV</button>
</form>
<div class="pg-card"><span class="pg-eyebrow">SAFE EXPORT</span><h2>Organization membership report</h2><p class="muted">Export includes organization status, membership level, term state, billing-period end, final access expiry and auto-renew flag. Cells are escaped against spreadsheet formula injection.</p><a class="button button-primary" href="{{ route('tenant.member-operations.export') }}">Download CSV</a></div>
</div>

<form method="get" class="pg-card form-inline" role="search">
<input name="q" value="{{ request('q') }}" placeholder="Search display name or email">
<select name="status"><option value="">All organization states</option><option value="active" @selected(request('status')==='active')>Active</option><option value="suspended" @selected(request('status')==='suspended')>Suspended</option><option value="pending" @selected(request('status')==='pending')>Pending</option></select>
<button class="button button-ghost">Filter</button>@if(request()->hasAny(['q','status']))<a class="button button-ghost" href="{{ route('tenant.member-operations.index') }}">Clear</a>@endif
</form>

<form method="post" action="{{ route('tenant.member-operations.bulk') }}" class="pg-card form-stack">@csrf
<div class="row-between"><div><span class="pg-eyebrow">BULK ACTION</span><h2>Select members</h2></div><span class="muted">Choose up to 500 members per operation.</span></div>
<div class="form-inline">
<select name="action" required><option value="">Choose action</option><option value="assign_level">Assign/change level</option><option value="renew">Renew/extend structured terms</option><option value="suspend">Suspend organization membership</option><option value="reactivate">Reactivate organization membership</option></select>
<select name="membership_level_id"><option value="">Membership level when assigning</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }} · {{ $level->priceLabel() }}</option>@endforeach</select>
<label class="row-actions"><input type="checkbox" name="auto_renew" value="1"> Auto-renew when assigning</label>
<input name="reason" maxlength="1000" placeholder="Reason / internal note (optional)">
<button class="button button-primary">Apply to selected</button>
</div>
<div class="pg-privacy-note">Suspension is scoped to {{ $tenant->name }}. It does not suspend the member’s Private Gather account or memberships in other organizations. Expired/cancelled terms must be renewed or reassigned before reactivation.</div>

<div style="overflow-x:auto"><table class="pg-table"><thead><tr><th>Select</th><th>Member</th><th>Organization status</th><th>Level</th><th>Term</th><th>Period / access end</th></tr></thead><tbody>
@forelse($members as $member)
@php($term=$terms->get($member->id))
<tr>
<td><input type="checkbox" name="user_ids[]" value="{{ $member->id }}" aria-label="Select {{ $member->display_name ?: $member->name }}"></td>
<td><strong>{{ $member->display_name ?: $member->name }}</strong><br><small>{{ $member->email }}</small></td>
<td><span class="pg-pill">{{ ucfirst($member->pivot->status) }}</span></td>
<td>{{ $term?->level?->name ?? 'Legacy / unstructured' }}</td>
<td>{{ $term ? ucfirst($term->status) : 'Legacy' }}@if($term?->auto_renew) · auto-renew@endif</td>
<td>@if($term?->current_period_ends_at)Period {{ $term->current_period_ends_at->format('M j, Y') }}@else—@endif @if($term?->expires_at && (!$term->current_period_ends_at || !$term->expires_at->equalTo($term->current_period_ends_at)))<br><small>Access {{ $term->expires_at->format('M j, Y') }}</small>@endif</td>
</tr>
@empty<tr><td colspan="6"><div class="pg-empty"><h3>No members match this filter</h3></div></td></tr>@endforelse
</tbody></table></div>
{{ $members->links() }}
</form>
</div></section>
@endsection
