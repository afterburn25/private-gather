@extends('layouts.app')
@section('title','Create Club or Organization')
@section('content')
<section class="auth-shell">
<form method="post" action="{{ route('organizations.store') }}" class="panel form-card">
@csrf

<div class="eyebrow">CREATE A PRIVATE GATHER WEBSITE</div>
<h1>Launch your club or organization</h1>
<p class="muted">Your branded website is provisioned immediately with its own Private Gather address, homepage, membership pages, event tools, navigation, and operator workspace.</p>

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
<strong>Club / Venue</strong>
<span class="muted">For a physical club, recurring venue, or nightlife business with memberships, tickets, and door operations.</span>
</label>
<label>
<input type="radio" name="type" value="organization" @checked(old('type')==='organization')>
<strong>Organization / Community</strong>
<span class="muted">For lifestyle organizations, regional communities, travel groups, chapters, and private member networks.</span>
</label>
<label>
<input type="radio" name="type" value="private_host" @checked(old('type')==='private_host')>
<strong>Private Host</strong>
<span class="muted">For invitation-focused private events that should not appear in network discovery by default.</span>
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
<small class="muted">Spaces and capital letters are automatically converted to a web-safe address. A custom domain can be connected after launch.</small>
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
<input name="tagline" value="{{old('tagline')}}" maxlength="180" placeholder="Private events. Real community. One place to belong.">
<small class="muted">This becomes the starter hero message and can be changed later in the Website Builder.</small>
</label>

<label>Starter design
<select name="template">
<option value="midnight" @selected(old('template','midnight')==='midnight')>Midnight — premium dark</option>
<option value="velvet" @selected(old('template')==='velvet')>Velvet — warm luxury</option>
<option value="noir" @selected(old('template')==='noir')>Noir — minimal nightlife</option>
<option value="modern" @selected(old('template')==='modern')>Modern — clean social</option>
</select>
</label>

<input type="hidden" name="marketplace_enabled" value="0">
<label>
<input type="checkbox" name="marketplace_enabled" value="1" @checked(old('marketplace_enabled','1')==='1')>
<strong>List this site and its public events in Private Gather discovery</strong>
<span class="muted">You can change network visibility later. Private member content remains access-controlled.</span>
</label>

<div class="panel">
<strong>Included immediately</strong>
<p class="muted">Home, About, Membership, Rules/Guidelines, Contact, Events, branded navigation, operator dashboard, member access, analytics foundation, and managed Private Gather subdomain.</p>
</div>

<button class="btn primary" type="submit">Create Website</button>
</form>
</section>
@endsection
