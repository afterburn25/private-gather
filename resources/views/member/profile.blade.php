@extends('layouts.app')
@section('title','Profile & Privacy')
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">IDENTITY & PRIVACY</span><h1>Shape how you show up.</h1><p>Your profile is a social identity, not a public record. Share what helps you connect while keeping sensitive account information private.</p></div></section>
<section class="pg-page"><div class="pg-shell pg-grid" style="grid-template-columns:minmax(0,1.4fr) minmax(280px,.6fr);align-items:start">
<form method="post" action="{{ route('profile.update') }}" class="pg-card form-stack">@csrf @method('put')
    <span class="pg-eyebrow">PROFILE</span><h2>About you</h2>
    <div class="form-grid">
        <label>Display name<input name="display_name" value="{{ old('display_name',auth()->user()->display_name) }}" required><small class="muted">This is the name other members see.</small></label>
        <label>Profile type<select name="profile_type"><option value="individual" @selected(($profile?->profile_type)==='individual')>Individual</option><option value="couple" @selected(($profile?->profile_type)==='couple')>Couple / linked profile</option></select><small class="muted">Used for community context and eligible ticket types.</small></label>
        <label>City<input name="city" value="{{ old('city',$profile?->city) }}" autocomplete="address-level2"></label>
        <label>State / region<input name="region" value="{{ old('region',$profile?->region) }}" autocomplete="address-level1"></label>
        <label class="span2">Headline<input name="headline" value="{{ old('headline',$profile?->headline) }}" placeholder="A short introduction"></label>
        <label class="span2">About<textarea name="bio" rows="7" placeholder="Share the parts of your personality and interests that help the right people understand you.">{{ old('bio',$profile?->bio) }}</textarea></label>
        <label class="span2">Interests<input name="interests" value="{{ old('interests',implode(', ',$profile?->interests??[])) }}" placeholder="Socials, travel, dancing, live music"></label>
    </div>
    <div class="pg-card" style="background:rgba(255,255,255,.025)!important"><span class="pg-eyebrow">DISCOVERY</span><label class="row-actions" style="margin-top:10px"><input type="checkbox" name="discoverable" value="1" @checked($profile?->discoverable ?? true)><span><strong>Allow this profile to appear in discovery</strong><br><small class="muted">Turning this off does not remove memberships, event records or direct conversations.</small></span></label></div>
    <button class="button button-primary">Save profile</button>
</form>
<aside class="pg-grid" style="position:sticky;top:140px">
    <section class="pg-card"><span class="pg-eyebrow">PRIVATE BY DEFAULT</span><h2>What stays account-only</h2><p class="muted">Your date of birth, password, security credentials and other account-security data are not profile content and are not rendered here.</p></section>
    <section class="pg-card"><span class="pg-eyebrow">TRUST CONTEXT</span><h2>Recognition has an issuer.</h2><p class="muted">Private Gather badges and club-issued badges are presented separately so members can tell who issued each signal.</p><a class="text-link" href="{{ route('member.badges.index') }}">View my badges →</a></section>
    <section class="pg-card"><span class="pg-eyebrow">ACCOUNT SAFETY</span><h2>Security controls</h2><p class="muted">Manage two-factor authentication, sessions and privacy requests separately from your social profile.</p><a class="button button-ghost" href="{{ route('member.security') }}">Security & privacy</a></section>
</aside>
</div></section>
@endsection
