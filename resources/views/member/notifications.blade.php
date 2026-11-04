@extends('layouts.app')
@section('title','Notifications')
@push('head')
<link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/product-completion.css') }}">
@endpush
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">MY PRIVATE GATHER</span>
<h1>Stay informed without being overwhelmed.</h1>
<p>Account, ticket, membership, message and organization notices stay inside your private inbox. Browser push remains intentionally generic on lock screens.</p>
<nav class="pc-chip-row" aria-label="Member tools">
<a href="{{ route('saved.index') }}">Saved</a>
<a href="{{ route('connections.index') }}">Connections</a>
<a href="{{ route('notifications.index') }}" aria-current="page">Notifications</a>
<a href="{{ route('privacy.index') }}">Privacy</a>
<a href="{{ route('member.security') }}">Security</a>
</nav>
</div></section>

<section class="pg-page"><div class="pg-shell">
@if(session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif

<div class="pc-two">
<section class="pg-card">
<div class="row-between">
<div><span class="pg-eyebrow">INBOX</span><h2>Notifications</h2></div>
<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="button button-ghost">Mark all read</button></form>
</div>

<div class="pg-grid">
@forelse($notifications as $note)
@php($data = json_decode($note->data, true) ?: [])
<form method="post" action="{{ route('notifications.read', $note->id) }}" class="pc-notification {{ $note->read_at ? '' : 'is-new' }}">
@csrf
@method('PATCH')
@if($note->type === 'tenant.announcement')
<div class="row-between" style="align-items:flex-start;gap:14px">
<span class="pg-pill">{{ strtoupper((string)($data['priority'] ?? 'normal')) }}</span>
@if(!empty($data['audience']))<span class="pg-pill">{{ strtoupper(str_replace('_',' ',(string)$data['audience'])) }}</span>@endif
</div>
<strong>{{ ($data['tenant_name'] ?? 'Private Gather organization').': '.($data['title'] ?? $data['label'] ?? 'Organization announcement') }}</strong>
@if(!empty($data['body']))<p style="white-space:pre-wrap;margin:.55rem 0 0">{{ $data['body'] }}</p>@endif
@if(!empty($data['expires_at']))<small>Time-limited notice · expires {{ \Carbon\Carbon::parse($data['expires_at'])->format('M j, Y g:i A') }}</small>@endif
@else
<strong>{{ $data['label'] ?? str_replace('.', ' ', ucfirst($note->type)) }}</strong>
@endif
<small>{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</small>
@if(!$note->read_at)<button>Mark read</button>@endif
</form>
@empty
<div class="pg-empty"><h3>You’re all caught up</h3><p>New account, ticket, membership and community notices will appear here.</p></div>
@endforelse
</div>
{{ $notifications->links() }}
</section>

<div class="pg-grid">
<form method="post" action="{{ route('notifications.preferences') }}" class="pg-card form-stack">
@csrf
@method('PATCH')
<span class="pg-eyebrow">PREFERENCES</span>
<h2>Choose your channels</h2>
@foreach([
'email_events' => 'Event email',
'email_messages' => 'Message email',
'email_membership' => 'Membership email',
'email_tickets' => 'Ticket email',
'browser_notifications' => 'Browser notifications',
'push_messages' => 'Message push',
'push_events' => 'Event push',
'push_membership' => 'Membership push',
'push_tickets' => 'Ticket push',
'email_marketing' => 'Optional marketing email',
] as $field => $label)
<label class="row-actions"><input type="checkbox" name="{{ $field }}" value="1" @checked($prefs?->$field ?? ($field !== 'email_marketing'))>{{ $label }}</label>
@endforeach
<label>Digest frequency
<select name="digest_frequency">
<option value="instant" @selected(($prefs?->digest_frequency ?? 'instant') === 'instant')>Instant</option>
<option value="daily" @selected(($prefs?->digest_frequency ?? '') === 'daily')>Daily digest</option>
<option value="weekly" @selected(($prefs?->digest_frequency ?? '') === 'weekly')>Weekly digest</option>
</select>
</label>
<button class="button button-primary">Save preferences</button>
</form>

<section class="pg-card" data-push-controls data-push-public-key="{{ $pushPublicKey }}" data-push-store="{{ route('notifications.push.store') }}" data-push-remove="{{ route('notifications.push.destroy') }}">
<span class="pg-eyebrow">THIS DEVICE</span>
<h2>Private browser push</h2>
<p class="muted">Enrollment is explicit per browser. Lock-screen messages stay intentionally generic; sensitive details require opening the authenticated app.</p>
<div class="pg-actions">
<button type="button" class="button button-primary" data-push-enable>Enable push</button>
<button type="button" class="button button-ghost" data-push-disable @if(($pushCount ?? 0) === 0) hidden @endif>Disable on this browser</button>
</div>
<p data-push-status class="pg-privacy-note">{{ ($pushCount ?? 0) > 0 ? $pushCount.' browser subscription(s) are saved for your account.' : 'No browser push subscription is currently saved.' }}</p>
</section>
</div>
</div>
</div></section>
@endsection
