@extends('layouts.app')
@section('title','Membership Invitations — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">MEMBERSHIP OPERATIONS</span>
<h1>Membership invitations</h1>
<p>Invite an existing Private Gather account directly into an approved membership level. Invitations are tenant-bound, expire automatically, and never reuse staff-access tokens.</p>
<div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.membership-levels.index') }}">Membership levels</a><a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Club OS</a></div>
</div></section>
<section class="pg-page"><div class="pg-shell pg-grid" style="grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);align-items:start">
<form method="post" action="{{ route('tenant.membership-invitations.store') }}" class="pg-card form-stack">
@csrf
<span class="pg-eyebrow">NEW INVITATION</span><h2>Invite a member</h2>
<label>Private Gather account email<input type="email" name="email" value="{{ old('email') }}" required></label>
<label>Membership level<select name="membership_level_id" required>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }} · {{ $level->priceLabel() }}</option>@endforeach</select></label>
<label>Expires in<select name="expires_days"><option value="3">3 days</option><option value="7" selected>7 days</option><option value="14">14 days</option><option value="30">30 days</option></select></label>
<label>Optional message<textarea name="message" rows="4" maxlength="1500">{{ old('message') }}</textarea></label>
<button class="button button-primary">Create & send invitation</button>
<div class="pg-privacy-note">The account must already exist and satisfy this organization’s minimum age and profile eligibility. A newly created invitation revokes any older pending invitation for the same email.</div>
@if(session('membership_invite_link'))<div class="pg-privacy-note"><strong>Private invitation link</strong><br><code style="word-break:break-all">{{ session('membership_invite_link') }}</code><p class="muted">Use only if email delivery failed or you intentionally send it through a trusted channel.</p></div>@endif
</form>
<section class="pg-card"><span class="pg-eyebrow">INVITATION HISTORY</span><h2>Recent invitations</h2>
@forelse($invitations as $invitation)
<div class="list-row"><div><strong>{{ $invitation->email }}</strong><small>{{ $invitation->level?->name ?? 'Default level on acceptance' }} · {{ ucfirst($invitation->status) }} · expires {{ $invitation->expires_at?->format('M j, Y g:i A') }}</small>@if($invitation->acceptedBy)<small>Accepted by {{ $invitation->acceptedBy->display_name ?: $invitation->acceptedBy->email }}</small>@endif</div>
@if($invitation->status === \App\Models\TenantMembershipInvitation::STATUS_PENDING)<form method="post" action="{{ route('tenant.membership-invitations.revoke',$invitation) }}">@csrf @method('DELETE')<button class="button button-ghost" data-confirm="Revoke this membership invitation?">Revoke</button></form>@endif</div>
@empty<div class="pg-empty"><h3>No membership invitations yet</h3><p>Invite-only and curated organizations can use this workflow instead of accepting open applications.</p></div>@endforelse
{{ $invitations->links() }}
</section>
</div></section>
@endsection
