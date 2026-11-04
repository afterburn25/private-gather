@extends('layouts.app')
@section('title','Create Club or Organization')
@section('content')
@php
    $selectedProfiles = (array) old('membership_profiles', ['couple', 'individual']);
@endphp
<section class="auth-shell">
<form method="post" action="{{ route('organizations.store') }}" class="panel form-card">
@csrf

<div class="eyebrow">CREATE YOUR WEBSITE</div>
<h1>Launch your club or organization</h1>
<p class="muted">Build a first-class Private Gather website with its own address, branding, membership rules, event defaults, privacy controls and operator workspace. Your Private Gather subdomain is live immediately; an optional custom domain can be verified after launch.</p>

@if($errors->any())
<div class="alert alert-danger" role="alert">
<strong>We couldn't create the website yet.</strong>
<ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul>
</div>
@endif

<details class="panel" open>
<summary><strong>1 · Organization Type</strong></summary>
<p class="muted">Choose the operating model that best matches this website.</p>
<label>
<input type="radio" name="type" value="club" @checked(old('type','club')==='club')>
<strong>Club / Lifestyle Venue</strong>
<span class="muted">For a physical club or recurring venue with memberships, events, tickets, guests and door operations.</span>
</label>
<label>
<input type="radio" name="type" value="organization" @checked(old('type')==='organization')>
<strong>Organization / Community</strong>
<span class="muted">For regional communities, travel groups, chapters, social organizations and private member networks.</span>
</label>
<label>
<input type="radio" name="type" value="private_host" @checked(old('type')==='private_host')>
<strong>Private Host</strong>
<span class="muted">For invitation-focused private gatherings that should stay out of public network discovery by default.</span>
</label>
</details>

<details class="panel" open>
<summary><strong>2 · Name & Private Gather Address</strong></summary>
<label>Name
<input name="name" value="{{old('name')}}" required maxlength="150" placeholder="Club Euphoria">
</label>
<label>Private Gather address
<div class="domain-input">
<input name="subdomain" value="{{old('subdomain')}}" required maxlength="63" placeholder="club-euphoria">
<span>.{{ config('platform.root_domain') }}</span>
</div>
<small class="muted">This address stays with the site even if you later make a custom domain primary.</small>
</label>
<div class="form-grid">
<label>City
<input name="city" value="{{old('city')}}" maxlength="120" placeholder="Dallas">
</label>
<label>State / Region
<input name="region" value="{{old('region')}}" maxlength="120" placeholder="Texas">
</label>
</div>
</details>

<details class="panel" open>
<summary><strong>3 · Branding</strong></summary>
<label>Tagline
<input name="tagline" value="{{old('tagline')}}" maxlength="180" placeholder="Private events. Real community. Respect and discretion.">
<small class="muted">This becomes the starter homepage message and remains editable in Website Builder.</small>
</label>
<div class="form-grid">
<label>Primary color
<input type="color" name="primary_color" value="{{old('primary_color','#171319')}}">
</label>
<label>Accent color
<input type="color" name="accent_color" value="{{old('accent_color','#9A3456')}}">
</label>
</div>
<p class="muted">Your own logo and favicon can be uploaded after launch from Branding & Theme.</p>
</details>

<details class="panel" open>
<summary><strong>4 · Choose Design</strong></summary>
<label>Website theme
<select name="theme" required>
@foreach($tenantThemes as $key => $theme)
<option value="{{$key}}" @selected(old('theme', \App\Support\ThemeCatalog::TENANT_DEFAULT)===$key)>{{$theme['label']}} — {{$theme['description']}}</option>
@endforeach
</select>
</label>
<p class="muted">Tenant websites use their own approved theme catalog and do not simply reskin the central Private Gather marketplace.</p>
</details>

<details class="panel" open>
<summary><strong>5 · Membership Setup</strong></summary>
<div class="form-grid">
<label>Membership access
<select name="membership_access">
<option value="application" @selected(old('membership_access','application')==='application')>Application & approval</option>
<option value="open" @selected(old('membership_access')==='open')>Open enrollment</option>
<option value="invite_only" @selected(old('membership_access')==='invite_only')>Invitation only</option>
</select>
</label>
<label>Minimum age
<select name="minimum_age">
<option value="18" @selected((string)old('minimum_age','18')==='18')>18+</option>
<option value="21" @selected((string)old('minimum_age')==='21')>21+</option>
<option value="25" @selected((string)old('minimum_age')==='25')>25+</option>
</select>
</label>
</div>
<fieldset>
<legend>Membership profiles</legend>
<label><input type="checkbox" name="membership_profiles[]" value="couple" @checked(in_array('couple',$selectedProfiles,true))> Couples</label>
<label><input type="checkbox" name="membership_profiles[]" value="individual" @checked(in_array('individual',$selectedProfiles,true))> Individuals</label>
</fieldset>
</details>

<details class="panel" open>
<summary><strong>6 · Event Setup</strong></summary>
<div class="form-grid">
<label>Default event access
<select name="event_access_default">
<option value="members" @selected(old('event_access_default','members')==='members')>Members only</option>
<option value="public" @selected(old('event_access_default')==='public')>Public</option>
<option value="approval" @selected(old('event_access_default')==='approval')>Approval required</option>
<option value="invite_only" @selected(old('event_access_default')==='invite_only')>Invite only</option>
</select>
</label>
<label>Venue address visibility
<select name="venue_address_visibility">
<option value="approved_or_ticketed" @selected(old('venue_address_visibility','approved_or_ticketed')==='approved_or_ticketed')>Approved or ticketed attendees</option>
<option value="members" @selected(old('venue_address_visibility')==='members')>Members</option>
<option value="public" @selected(old('venue_address_visibility')==='public')>Public</option>
</select>
</label>
</div>
<p class="muted">These are safe starting defaults. Each event can still use its own audience, approval, ticketing and location rules.</p>
</details>

<details class="panel" open>
<summary><strong>7 · Privacy & Discovery</strong></summary>
<div class="form-grid">
<label>Private Gather network visibility
<select name="network_visibility">
<option value="listed" @selected(old('network_visibility','listed')==='listed')>Listed in Private Gather discovery</option>
<option value="private" @selected(old('network_visibility')==='private')>Private / unlisted</option>
<option value="invite_only" @selected(old('network_visibility')==='invite_only')>Invitation only</option>
</select>
</label>
<label>Member directory
<select name="member_directory_visibility">
<option value="members" @selected(old('member_directory_visibility','members')==='members')>Members only</option>
<option value="verified" @selected(old('member_directory_visibility')==='verified')>Verified members only</option>
<option value="disabled" @selected(old('member_directory_visibility')==='disabled')>Disabled</option>
<option value="public" @selected(old('member_directory_visibility')==='public')>Public, subject to each member's visibility choice</option>
</select>
</label>
<label>Event attendance visibility
<select name="event_attendance_visibility">
<option value="members" @selected(old('event_attendance_visibility','members')==='members')>Members</option>
<option value="attendees" @selected(old('event_attendance_visibility')==='attendees')>Event attendees only</option>
<option value="private" @selected(old('event_attendance_visibility')==='private')>Private</option>
</select>
</label>
</div>
<p class="muted">Individual member privacy controls remain authoritative even when a tenant enables a directory or attendee list. Private Host launches are forced unlisted at creation even if a stale browser value says otherwise.</p>
</details>

<details class="panel" open>
<summary><strong>8 · Optional Custom Domain</strong></summary>
<label>Domain you already own
<input name="custom_domain" value="{{old('custom_domain')}}" maxlength="253" placeholder="www.clubeuphoria.com">
</label>
<p class="muted">Optional. Private Gather will add it in pending state and show the DNS verification instructions after launch. HTTPS becomes eligible only after ownership/DNS verification succeeds.</p>
<p class="muted">If you need to register a new domain instead, launch the website first and then use Domain Marketplace from that site's Club OS workspace.</p>
</details>

<details class="panel" open>
<summary><strong>9 · Preview</strong></summary>
<div class="panel">
<strong>Your launch package includes</strong>
<p class="muted">A permanent Private Gather subdomain, HTTPS-managed platform hostname, selected tenant theme and colors, Home, About, Membership, First Visit, Rules & Consent, Privacy and Contact pages, event discovery, member access, operator dashboard, CMS/navigation/media tools, analytics foundation and the privacy defaults selected above.</p>
<p class="muted">After creation, use <strong>Preview Website</strong> from Club OS before promoting your custom domain or public discovery.</p>
</div>
</details>

<details class="panel" open>
<summary><strong>10 · Launch</strong></summary>
<p class="muted">Your Private Gather address is provisioned immediately. A custom domain, if supplied, remains pending until DNS verification. Nothing in this step exposes private venue addresses or overrides member privacy choices.</p>
<button class="btn primary" type="submit">Launch Website</button>
</details>
</form>
</section>
@endsection
