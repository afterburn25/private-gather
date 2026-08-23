@extends('layouts.app')
@section('title','Security & Privacy')
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">ACCOUNT SAFETY</span><h1>Security without spectacle.</h1><p>Keep social identity separate from account security. These controls affect authentication, sessions and privacy requests—not what you choose to share on your profile.</p></div></section>
<section class="pg-page"><div class="pg-shell pg-grid pg-grid-2">
    <section class="pg-card"><div class="row-between"><div><span class="pg-eyebrow">TWO-FACTOR AUTHENTICATION</span><h2>Authenticator protection</h2></div>@if($user->two_factor_confirmed_at)<span class="pg-pill pg-pill-good">Enabled</span>@else<span class="pg-pill pg-pill-warn">Not enabled</span>@endif</div>
        @if($user->two_factor_confirmed_at)
            <p class="muted">Enabled since {{ $user->two_factor_confirmed_at->format('M j, Y') }}. Current-password confirmation is required to turn it off.</p>
            <form class="form-stack" method="post" action="{{ route('member.security.2fa.disable') }}">@csrf @method('delete')<label>Current password<input name="password" type="password" autocomplete="current-password" required></label><button class="button pg-button-danger">Disable 2FA</button></form>
        @else
            <p class="muted">Add an authenticator as a second factor for account access.</p>
            <form class="form-stack" method="post" action="{{ route('member.security.2fa.begin') }}">@csrf<label>Current password<input name="password" type="password" autocomplete="current-password" required></label><button class="button button-primary">Set up authenticator</button></form>
        @endif
    </section>

    <section class="pg-card"><span class="pg-eyebrow">SIGNED-IN DEVICES</span><h2>Other sessions</h2><p class="muted">Invalidate other database-backed sessions while keeping this browser signed in. Use this after a lost device or any sign-in you no longer trust.</p><form class="form-stack" method="post" action="{{ route('member.security.sessions.revoke') }}">@csrf<label>Current password<input name="password" type="password" autocomplete="current-password" required></label><button class="button button-ghost">Sign out other sessions</button></form></section>

    <section class="pg-card"><span class="pg-eyebrow">SECURITY ACTIVITY</span><h2>Recent account events</h2><div class="pg-grid" style="gap:10px">@forelse($securityEvents as $securityEvent)<div class="row-between"><div><strong>{{ ucwords(str_replace(['.','_'], ' ', $securityEvent->event)) }}</strong><small>{{ $securityEvent->occurred_at?->format('M j, Y · g:i A') }}</small></div><span class="pg-pill">Recorded</span></div>@empty<div class="pg-empty">No security activity has been recorded yet.</div>@endforelse</div></section>

    <section class="pg-card"><span class="pg-eyebrow">YOUR DATA</span><h2>Privacy requests</h2><p class="muted">Request a data export or begin an account deletion request. Deletion requires current-password confirmation.</p><form class="form-stack" method="post" action="{{ route('member.security.data') }}">@csrf<label>Request<select name="type"><option value="export">Request data export</option><option value="delete">Request account deletion</option></select></label><label>Current password<input name="password" type="password" autocomplete="current-password" placeholder="Required for deletion"></label><button class="button button-ghost">Submit privacy request</button></form></section>
</div>
<div class="pg-shell" style="margin-top:18px"><div class="pg-privacy-note">Private Gather never needs to display your password, authenticator secret or recovery credentials on your social profile. Security controls stay in this account-only area.</div></div>
</section>
@endsection
