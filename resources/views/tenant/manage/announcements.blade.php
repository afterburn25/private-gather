@extends('layouts.app')
@section('title','Announcements — '.$tenant->name)
@section('content')
@php
$eventMap=$events->keyBy('id');
$levelMap=$membershipLevels->keyBy('id');
$chapterMap=$chapters->keyBy('id');
@endphp
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">CLUB OS · COMMUNICATIONS</span>
<h1>Announcements</h1>
<p>Publish or schedule operational notices to active members, staff, membership tiers, chapters or approved event attendees inside Private Gather. Marketing email and SMS consent remain separate.</p>
<div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Back to Club OS</a>@if(\Illuminate\Support\Facades\Route::has('tenant.communications.index'))<a class="button button-primary" href="{{ route('tenant.communications.index') }}">Communications Center</a>@endif<a class="button button-ghost" href="{{ route('tenant.growth.index') }}">Growth & referrals</a></div>
</div></section>

<section class="pg-page"><div class="pg-shell">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Check the announcement details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="pg-grid pg-grid-2">
<div class="pg-card">
<span class="pg-eyebrow">NEW NOTICE</span>
<h2>Create announcement</h2>
<form method="post" action="{{ route('tenant.announcements.store') }}" class="form-card">
@csrf
<label>Title
<input name="title" maxlength="180" required value="{{ old('title') }}" placeholder="Saturday arrival update">
</label>
<label>Message
<textarea name="body" rows="8" maxlength="20000" required placeholder="Share the operational update members need to know.">{{ old('body') }}</textarea>
</label>
<div class="form-grid">
<label>Audience
<select name="audience" required>
<option value="all_members" @selected(old('audience','all_members')==='all_members')>All active members</option>
<option value="staff" @selected(old('audience')==='staff')>Staff only</option>
<option value="membership_level" @selected(old('audience')==='membership_level')>Membership level / VIP tier</option>
@if($chapters->isNotEmpty())<option value="chapter_members" @selected(old('audience')==='chapter_members')>Chapter members</option>@endif
<option value="event_attendees" @selected(old('audience')==='event_attendees')>Approved event attendees</option>
</select>
</label>
<label>Priority
<select name="priority" required>
<option value="normal" @selected(old('priority','normal')==='normal')>Normal</option>
<option value="important" @selected(old('priority')==='important')>Important</option>
<option value="urgent" @selected(old('priority')==='urgent')>Urgent</option>
</select>
</label>
<label>Membership level
<select name="membership_level_id"><option value="">Only when audience is Membership level</option>@foreach($membershipLevels as $level)<option value="{{ $level->id }}" @selected((string)old('membership_level_id')===(string)$level->id)>{{ $level->name }}</option>@endforeach</select>
</label>
<label>Chapter
<select name="community_group_id"><option value="">Only when audience is Chapter members</option>@foreach($chapters as $chapter)<option value="{{ $chapter->id }}" @selected((string)old('community_group_id')===(string)$chapter->id)>{{ $chapter->name }}</option>@endforeach</select>
</label>
<label>Event
<select name="event_id"><option value="">Only when audience is Approved event attendees</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected((string)old('event_id')===(string)$event->id)>{{ $event->title }}</option>@endforeach</select>
</label>
<label>Status
<select name="status" required><option value="draft" @selected(old('status','draft')==='draft')>Draft</option><option value="scheduled" @selected(old('status')==='scheduled')>Schedule notice</option><option value="published" @selected(old('status')==='published')>Publish now</option></select>
</label>
<label>Schedule / publish time
<input type="datetime-local" name="published_at" value="{{ old('published_at') }}">
</label>
<label>Expires
<input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">
</label>
</div>
<button type="submit" class="button button-primary">Save announcement</button>
</form>
</div>

<div class="pg-card">
<span class="pg-eyebrow">DELIVERY RULES</span>
<h2>Private by default</h2>
<ul class="muted">
<li>Member notices are delivered only to active members in the selected audience.</li>
<li>Chapter audiences are resolved from active chapter membership and active parent-organization membership.</li>
<li>Event audiences use approved/confirmed attendee state, not merely an RSVP attempt.</li>
<li>Marketing email and SMS are separate workflows and require explicit channel consent.</li>
<li>Detailed notice content stays inside authenticated Private Gather surfaces; browser push remains generic.</li>
</ul>
</div>
</div>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">NOTICE HISTORY</span><h2>Announcements</h2><p>Draft, scheduled and published notices remain tenant-scoped and auditable.</p></div></div>
<div class="pg-grid pg-grid-2">
@forelse($announcements as $announcement)
<article class="pg-card">
<div class="row-between"><div><span class="pg-pill">{{ ucfirst($announcement->status) }}</span><span class="pg-pill">{{ ucfirst($announcement->priority) }}</span></div><span class="muted">{{ $announcement->recipient_count }} recipients</span></div>
<h3>{{ $announcement->title }}</h3>
<p class="muted">{{ \Illuminate\Support\Str::limit($announcement->body,220) }}</p>
<p class="muted"><strong>Audience:</strong> {{ str_replace('_',' ',ucfirst($announcement->audience)) }}
@if($announcement->audience==='event_attendees' && $announcement->event_id) · {{ $eventMap->get($announcement->event_id)?->title ?: 'Event unavailable' }}@endif
@if($announcement->audience==='membership_level' && $announcement->membership_level_id) · {{ $levelMap->get($announcement->membership_level_id)?->name ?: 'Membership level unavailable' }}@endif
@if($announcement->audience==='chapter_members' && $announcement->community_group_id) · {{ $chapterMap->get($announcement->community_group_id)?->name ?: 'Chapter unavailable' }}@endif
</p>
@if($announcement->published_at)<p class="muted">{{ $announcement->status==='scheduled' ? 'Scheduled' : 'Published' }}: {{ \Carbon\Carbon::parse($announcement->published_at)->format('M j, Y · g:i A') }}</p>@endif
@if($announcement->expires_at)<p class="muted">Expires: {{ \Carbon\Carbon::parse($announcement->expires_at)->format('M j, Y · g:i A') }}</p>@endif
@if($announcement->status!=='published')<form method="post" action="{{ route('tenant.announcements.publish',$announcement->id) }}">@csrf<button class="button button-primary" type="submit">Publish now</button></form>@endif
</article>
@empty
<div class="pg-card"><h3>No announcements yet</h3><p class="muted">Create the first operational notice above.</p></div>
@endforelse
</div>
{{ $announcements->links() }}
</section>
</div></section>
@endsection
