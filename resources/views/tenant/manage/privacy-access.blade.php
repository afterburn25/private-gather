@extends('layouts.app')
@section('title','Privacy & Access — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">CLUB OS · POLICY</span>
<h1>Privacy, access & discovery</h1>
<p>Control how people join, what new events default to, whether this organization appears in Private Gather discovery, and how member/attendance information may be exposed. Individual member privacy can always restrict further.</p>
<div class="pg-actions" style="margin-top:20px"><a class="button button-ghost" href="{{route('tenant.dashboard')}}">Back to Club OS</a></div>
</div></section>

<section class="pg-page"><div class="pg-shell narrow">
@if(session('status'))<div class="success" role="status">{{session('status')}}</div>@endif
@if($errors->any())<div class="error" role="alert"><strong>Settings were not changed.</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif

<form method="post" action="{{route('tenant.privacy-access.update')}}" class="form-stack">
@csrf
@method('PATCH')

<section class="pg-card form-stack">
<span class="pg-eyebrow">MEMBERSHIP</span>
<h2>Who can join</h2>
<div class="form-grid">
<label>Membership access
<select name="membership_access" required>
<option value="application" @selected(old('membership_access',$policy['membership_access'])==='application')>Application & approval</option>
<option value="open" @selected(old('membership_access',$policy['membership_access'])==='open')>Open enrollment</option>
<option value="invite_only" @selected(old('membership_access',$policy['membership_access'])==='invite_only')>Invitation only</option>
</select>
<small>Open enrollment still requires age eligibility and a saved Private Gather profile.</small>
</label>
<label>Minimum age
<input type="number" name="minimum_age" min="18" max="99" value="{{old('minimum_age',$policy['minimum_age'])}}" required>
<small>This may be stricter than Private Gather's platform-wide adult minimum.</small>
</label>
</div>
@php($profiles=(array)old('membership_profiles',$policy['membership_profiles']))
<fieldset><legend>Eligible membership profiles</legend>
<label class="row-actions"><input type="checkbox" name="membership_profiles[]" value="couple" @checked(in_array('couple',$profiles,true))> Couples</label>
<label class="row-actions"><input type="checkbox" name="membership_profiles[]" value="individual" @checked(in_array('individual',$profiles,true))> Individuals</label>
</fieldset>
</section>

<section class="pg-card form-stack">
<span class="pg-eyebrow">EVENT DEFAULTS</span>
<h2>Safe starting rules for new events</h2>
<div class="form-grid">
<label>Default event access
<select name="event_access_default" required>
<option value="members" @selected(old('event_access_default',$policy['event_access_default'])==='members')>Members only</option>
<option value="public" @selected(old('event_access_default',$policy['event_access_default'])==='public')>Public</option>
<option value="approval" @selected(old('event_access_default',$policy['event_access_default'])==='approval')>Public with approval</option>
<option value="invite_only" @selected(old('event_access_default',$policy['event_access_default'])==='invite_only')>Invite only</option>
</select>
</label>
<label>Default exact-address access
<select name="venue_address_visibility" required>
<option value="approved_or_ticketed" @selected(old('venue_address_visibility',$policy['venue_address_visibility'])==='approved_or_ticketed')>Approved RSVP or ticket holder</option>
<option value="members" @selected(old('venue_address_visibility',$policy['venue_address_visibility'])==='members')>Active members</option>
<option value="public" @selected(old('venue_address_visibility',$policy['venue_address_visibility'])==='public')>Public</option>
</select>
</label>
</div>
<p class="muted">Changing these defaults does not silently rewrite existing events. Each event keeps its current policy until an operator edits it.</p>
</section>

<section class="pg-card form-stack">
<span class="pg-eyebrow">PRIVATE GATHER NETWORK</span>
<h2>Discovery & community visibility</h2>
<label>Network visibility
<select name="network_visibility" required>
@if($tenant->type !== \App\Models\Tenant::TYPE_PRIVATE_HOST)
<option value="listed" @selected(old('network_visibility',$policy['network_visibility'])==='listed')>Listed in Private Gather discovery</option>
@endif
<option value="private" @selected(old('network_visibility',$policy['network_visibility'])==='private')>Private / unlisted</option>
<option value="invite_only" @selected(old('network_visibility',$policy['network_visibility'])==='invite_only')>Invitation only</option>
</select>
<small>
@if($tenant->type === \App\Models\Tenant::TYPE_PRIVATE_HOST)
Private Host sites remain outside public network discovery.
@endif
Private/unlisted and invitation-only sites may still be shared by their direct tenant URL.
</small>
</label>
<div class="form-grid">
<label>Member directory
<select name="member_directory_visibility" required>
<option value="disabled" @selected(old('member_directory_visibility',$policy['member_directory_visibility'])==='disabled')>Disabled</option>
<option value="members" @selected(old('member_directory_visibility',$policy['member_directory_visibility'])==='members')>Active members</option>
<option value="verified" @selected(old('member_directory_visibility',$policy['member_directory_visibility'])==='verified')>Verified members only</option>
<option value="public" @selected(old('member_directory_visibility',$policy['member_directory_visibility'])==='public')>Public policy, authenticated directory</option>
</select>
<small>Verified-only remains fail-closed until a genuine identity-verification source is configured; email verification is not identity verification.</small>
</label>
<label>Event attendance list
<select name="event_attendance_visibility" required>
<option value="private" @selected(old('event_attendance_visibility',$policy['event_attendance_visibility'])==='private')>Private</option>
<option value="members" @selected(old('event_attendance_visibility',$policy['event_attendance_visibility'])==='members')>Active members</option>
<option value="attendees" @selected(old('event_attendance_visibility',$policy['event_attendance_visibility'])==='attendees')>Approved/confirmed attendees only</option>
</select>
<small>Each attendee's own privacy and blocks can only make this more restrictive.</small>
</label>
</div>
</section>

<section class="pg-card">
<span class="pg-eyebrow">SAVE POLICY</span>
<h2>Apply to future access decisions</h2>
<p class="muted">This update is atomic and audited. Network listing state is synchronized with the selected discovery mode; unrelated tenant settings are preserved.</p>
<button class="button button-primary">Save Privacy & Access</button>
</section>
</form>
</div></section>
@endsection
