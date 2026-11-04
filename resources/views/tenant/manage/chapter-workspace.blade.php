@extends('layouts.app')
@section('title',$chapter->name.' Chapter Workspace — '.$tenant->name)
@section('content')
@php
$branding=$chapter->branding ?? [];
$parentManager=app(\App\Services\ChapterAccessService::class)->isParentManager($tenant,auth()->user());
@endphp
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">CHAPTER WORKSPACE · {{ strtoupper($tenant->name) }}</span><h1>{{ $chapter->name }}</h1>
<p>{{ $chapter->locationLabel() ?: 'Local chapter' }} · Lead: {{ $chapter->chapterLead?->display_name ?: $chapter->chapterLead?->name ?: 'Not assigned' }}</p>
<div class="pg-actions"><a class="button button-primary" href="{{ route('chapters.show',$chapter->slug) }}" target="_blank" rel="noopener">Open chapter page</a>@if($parentManager)<a class="button button-ghost" href="{{ route('tenant.chapters.index') }}">Organization chapters</a>@endif<a class="button button-ghost" href="{{ route('community.groups.show',$chapter) }}">Chapter discussion</a></div>
</div></section>
<section class="pg-page"><div class="pg-shell pg-grid">
<div class="pg-grid pg-grid-4"><div class="pg-stat"><span>Active members</span><strong>{{ $activeMembers }}</strong></div><div class="pg-stat"><span>Chapter events</span><strong>{{ $events->count() }}</strong></div><div class="pg-stat"><span>Check-ins</span><strong>{{ $eventAttendance }}</strong></div><div class="pg-stat"><span>Discussion posts</span><strong>{{ $postsCount }}</strong></div></div>

<div class="pg-grid pg-grid-2">
<form method="post" action="{{ route('tenant.chapters.branding',$chapter->id) }}" class="pg-card form-stack">@csrf @method('PATCH')
<span class="pg-eyebrow">CHAPTER PRESENTATION</span><h2>Optional local identity</h2><p class="muted">Chapter presentation can complement the parent organization but does not replace parent privacy, membership, payment or domain policy.</p>
<label>Tagline<input name="tagline" maxlength="240" value="{{ $branding['tagline'] ?? '' }}" placeholder="Chicago nights, one trusted community"></label>
<div class="form-grid"><label>Primary color<input name="primary_color" value="{{ $branding['primary_color'] ?? '' }}" placeholder="#d9bc78"></label><label>Accent color<input name="accent_color" value="{{ $branding['accent_color'] ?? '' }}" placeholder="#6d243f"></label></div>
<button class="button button-primary">Save chapter presentation</button></form>

<form method="post" action="{{ route('tenant.chapters.events.store',$chapter->id) }}" class="pg-card form-stack">@csrf
<span class="pg-eyebrow">CHAPTER EVENT</span><h2>Create a local event</h2><p class="muted">This creates the event under {{ $chapter->name }} using the parent organization’s default admission and exact-address privacy policy.</p>
<label>Title<input name="title" maxlength="180" required></label><label>Summary<input name="summary" maxlength="1000"></label>
<div class="form-grid"><label>Starts<input type="datetime-local" name="starts_at" required></label><label>Ends<input type="datetime-local" name="ends_at"></label><label>Capacity<input type="number" name="capacity" min="1"></label><label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label><label>City<input name="city" value="{{ $chapter->city }}"></label><label>State / Region<input name="region" value="{{ $chapter->region }}"></label></div>
<label>Public location label<input name="public_location_label" maxlength="190" placeholder="Downtown Chicago"></label><label>Exact address<textarea name="exact_address" rows="2" maxlength="500"></textarea></label><label>Description<textarea name="description" rows="4" maxlength="10000"></textarea></label>
<button class="button button-primary">Create chapter event</button></form>
</div>

<div class="pg-grid pg-grid-2">
<form method="post" action="{{ route('tenant.chapters.announcements.store',$chapter->id) }}" class="pg-card form-stack">@csrf
<span class="pg-eyebrow">CHAPTER COMMUNICATIONS</span><h2>Message chapter members</h2><p class="muted">Recipients are resolved only from active members of this chapter who are also active parent-organization members.</p>
<label>Title<input name="title" maxlength="180" required></label><label>Message<textarea name="body" rows="6" maxlength="30000" required></textarea></label>
<div class="form-grid"><label>Priority<select name="priority"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></select></label><label>Delivery<select name="delivery"><option value="draft">Draft</option><option value="now">Publish now</option><option value="scheduled">Schedule</option></select></label><label>Schedule time<input type="datetime-local" name="scheduled_at"></label><label>Expires<input type="datetime-local" name="expires_at"></label></div>
<button class="button button-primary">Save / send chapter announcement</button></form>

<section class="pg-card"><span class="pg-eyebrow">PARENT ROLLUP</span><h2>Recent chapter announcements</h2>@forelse($announcements as $announcement)<div class="list-row"><div><strong>{{ $announcement->title }}</strong><small>{{ ucfirst($announcement->status) }} · {{ ucfirst($announcement->priority) }}@if($announcement->published_at) · {{ \Carbon\Carbon::parse($announcement->published_at)->format('M j, Y g:i A') }}@endif · {{ $announcement->recipient_count }} recipients</small></div></div>@empty<p class="muted">No chapter announcements yet.</p>@endforelse</section>
</div>

<section class="pg-card"><div class="row-between"><div><span class="pg-eyebrow">CHAPTER CALENDAR</span><h2>Local events</h2></div>@if($parentManager)<a class="button button-ghost" href="{{ route('tenant.events.index') }}">Full Event Studio</a>@endif</div>
@forelse($events as $event)<div class="list-row"><div><strong>{{ $event->title }}</strong><small>{{ ucfirst($event->status) }} · {{ $event->starts_at?->format('M j, Y · g:i A') }} · {{ $event->city ?: $chapter->city ?: 'Location private' }}</small></div>@if($parentManager)<a class="button button-ghost" href="{{ route('tenant.events.edit',$event) }}">Advanced controls</a>@endif</div>@empty<p class="muted">No chapter events yet.</p>@endforelse</section>
</div></section>
@endsection
