@extends('layouts.app')
@section('title','Create Club or Organization')
@section('content')
<section class="auth-shell">
<form method="post" action="{{ route('organizations.store') }}" class="panel form-card">
@csrf

<div class="eyebrow">CREATE AN ADULT LIFESTYLE WEBSITE</div>
<h1>Launch your club or organization</h1>
<p class="muted">Create a private, professional home for your swinger club, lifestyle organization, regional community, or event group. Your site is provisioned immediately with its own Private Gather address, event tools, membership pages, privacy and consent guidance, and an operator workspace.</p>

@if($errors->any())
<div class="alert alert-danger" role="alert">
<strong>We couldn't create the website yet.</strong>
<ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul>
</div>
@endif

<fieldset>
<legend>Website type</legend>
<label>
<input type="radio" name="type" value="club" @checked(old('type','club')==='club')>
<strong>Swinger Club / Lifestyle Venue</strong>
<span class="muted">For a physical adult lifestyle club or recurring venue with memberships, guest approval, events, tickets, and door operations.</span>
</label>
<label>
<input type="radio" name="type" value="organization" @checked(old('type')==='organization')>
<strong>Lifestyle Organization / Community</strong>
<span class="muted">For swinger organizations, regional communities, travel groups, chapters, social groups, and private member networks.</span>
</label>
<label>
<input type="radio" name="type" value="private_host" @checked(old('type')==='private_host')>
<strong>Private Lifestyle Host</strong>
<span class="muted">For invitation-focused private events and house-party communities that should stay out of public network discovery by default.</span>
</label>
</fieldset>

<label>Name
<input name="name" value="{{old('name')}}" required maxlength="150" placeholder="Club Euphoria">
</label>

<label>Free Private Gather address
<div class="domain-input">
<input name="subdomain" value="{{old('subdomain')}}" required maxlength="63" placeholder="club-euphoria">
<span>.{{ config('platform.root_domain') }}</span>
</div>
<small class="muted">Spaces and capital letters are automatically converted to a web-safe address. You can connect your own domain after launch.</small>
</label>

<div class="form-grid">
<label>City
<input name="city" value="{{old('city')}}" maxlength="120" placeholder="Dallas">
</label>
<label>State / Region
<input name="region" value="{{old('region')}}" maxlength="120" placeholder="Texas">
</label>
</div>

<label>Tagline
<input name="tagline" value="{{old('tagline')}}" maxlength="180" placeholder="Private events. Real community. Respect and discretion.">
<small class="muted">This becomes the starter hero message and can be changed later in the Website Builder.</small>
</label>

<label>Starter design
<select name="template">
<option value="midnight" @selected(old('template','midnight')==='midnight')>Midnight — premium lifestyle club</option>
<option value="velvet" @selected(old('template')==='velvet')>Velvet — warm luxury</option>
<option value="noir" @selected(old('template')==='noir')>Noir — discreet minimal</option>
<option value="modern" @selected(old('template')==='modern')>Modern — clean community</option>
</select>
</label>

<input type="hidden" name="marketplace_enabled" value="0">
<label>
<input type="checkbox" name="marketplace_enabled" value="1" @checked(old('marketplace_enabled','1')==='1')>
<strong>List this club/organization and its public events in Private Gather discovery</strong>
<span class="muted">You can change network visibility later. Private member content, attendee visibility, and protected venue details remain separately controlled.</span>
</label>

<div class="panel">
<strong>Built for the lifestyle from day one</strong>
<p class="muted">Your starter site includes Home, About, Membership, First Visit, Rules & Consent, Privacy, Contact, Events, member access, branded navigation, operator tools, analytics foundation, and a managed Private Gather subdomain.</p>
</div>

<button class="btn primary" type="submit">Create Lifestyle Website</button>
</form>
</section>
@endsection
