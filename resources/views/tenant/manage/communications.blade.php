@extends('layouts.app')
@section('title','Communications Center — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">CLUB OS · COMMUNICATIONS</span>
<h1>Communications Center</h1>
<p>Build reusable audiences and templates, schedule consent-aware email campaigns, and keep marketing separate from operational member notices.</p>
<div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Back to Club OS</a><a class="button button-ghost" href="{{ route('tenant.growth.index') }}">Growth & referrals</a></div>
</div></section>

<section class="pg-page"><div class="pg-shell pg-grid">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Check the communications settings.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="pg-privacy-note"><strong>Consent boundary:</strong> marketing email is delivered only to contacts with active explicit email opt-in and no later opt-out. Operational notices are limited to active organization members and are labeled separately. Recipient eligibility is checked again immediately before each send.</div>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">AUDIENCES</span><h2>Reusable segments</h2><p>Save a segment once and reuse it for later campaigns. Target IDs are verified against this organization before the segment is created.</p></div></div>
<div class="pg-grid pg-grid-2">
<form method="post" action="{{ route('tenant.communications.segments.store') }}" class="pg-card form-stack">@csrf
<h3>Broad audience</h3><label>Name<input name="name" required maxlength="120" placeholder="All opted-in newsletter contacts"></label>
<label>Audience<select name="segment_type"><option value="all_opted_in">All explicitly opted-in marketing contacts</option><option value="all_members">All active organization members</option></select></label>
<label>Description<textarea name="description" rows="3" maxlength="1000"></textarea></label><button class="button button-primary">Save segment</button></form>

<form method="post" action="{{ route('tenant.communications.segments.store') }}" class="pg-card form-stack">@csrf
<h3>Chapter members</h3><input type="hidden" name="segment_type" value="chapter"><label>Name<input name="name" required maxlength="120" placeholder="Chicago chapter"></label><label>Chapter<select name="target_id" required><option value="">Choose chapter</option>@foreach($chapters as $chapter)<option value="{{ $chapter->id }}">{{ $chapter->name }}</option>@endforeach</select></label><label>Description<textarea name="description" rows="3" maxlength="1000"></textarea></label><button class="button button-primary">Save chapter segment</button></form>

<form method="post" action="{{ route('tenant.communications.segments.store') }}" class="pg-card form-stack">@csrf
<h3>Membership level</h3><input type="hidden" name="segment_type" value="membership_level"><label>Name<input name="name" required maxlength="120" placeholder="VIP members"></label><label>Level<select name="target_id" required><option value="">Choose level</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></label><label>Description<textarea name="description" rows="3" maxlength="1000"></textarea></label><button class="button button-primary">Save membership segment</button></form>

<form method="post" action="{{ route('tenant.communications.segments.store') }}" class="pg-card form-stack">@csrf
<h3>Event attendees</h3><input type="hidden" name="segment_type" value="event_attendees"><label>Name<input name="name" required maxlength="120" placeholder="Summer Social attendees"></label><label>Event<select name="target_id" required><option value="">Choose event</option>@foreach($events as $event)<option value="{{ $event->id }}">{{ $event->title }}@if($event->starts_at) · {{ \Carbon\Carbon::parse($event->starts_at)->format('M j, Y') }}@endif</option>@endforeach</select></label><label>Description<textarea name="description" rows="3" maxlength="1000"></textarea></label><button class="button button-primary">Save event segment</button></form>

<form method="post" action="{{ route('tenant.communications.segments.store') }}" class="pg-card form-stack">@csrf
<h3>CRM tag</h3><input type="hidden" name="segment_type" value="crm_tag"><label>Name<input name="name" required maxlength="120" placeholder="VIP CRM tag"></label><label>Tag<select name="target_id" required><option value="">Choose CRM tag</option>@foreach($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></label><label>Description<textarea name="description" rows="3" maxlength="1000"></textarea></label><button class="button button-primary">Save CRM segment</button></form>

<div class="pg-card"><h3>Saved audiences</h3>@forelse($segments as $segment)@php($definition=json_decode($segment->definition,true)?:[])<div class="list-row"><div><strong>{{ $segment->name }}</strong><small>{{ str_replace('_',' ',ucfirst($definition['type'] ?? 'segment')) }}@if(isset($definition['target_id'])) · target #{{ $definition['target_id'] }}@endif</small>@if($segment->description)<small>{{ $segment->description }}</small>@endif</div></div>@empty<p class="muted">No reusable audiences yet.</p>@endforelse</div>
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">TEMPLATES</span><h2>Reusable messages</h2><p>Templates are classified as marketing or operational so they cannot be silently reused across consent boundaries.</p></div></div>
<div class="pg-grid pg-grid-2">
<form method="post" action="{{ route('tenant.communications.templates.store') }}" class="pg-card form-stack">@csrf
<label>Template name<input name="name" required maxlength="120"></label><label>Classification<select name="message_type"><option value="marketing">Marketing / promotional</option><option value="operational">Operational member notice</option></select></label><label>Email subject<input name="subject" required maxlength="255"></label><label>Message<textarea name="body" required rows="8" maxlength="30000"></textarea></label><button class="button button-primary">Save template</button></form>
<div class="pg-card"><h3>Saved templates</h3>@forelse($templates as $template)<div class="list-row"><div><strong>{{ $template->name }}</strong><small>{{ ucfirst($template->message_type) }} · Email</small><small>{{ $template->subject }}</small></div></div>@empty<p class="muted">No reusable templates yet.</p>@endforelse</div>
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">CAMPAIGNS</span><h2>Draft, schedule or queue email</h2><p>A draft does not freeze recipients. When a campaign begins processing, recipient rows become an audit trail and eligibility is checked again before delivery.</p></div></div>
<form method="post" action="{{ route('tenant.communications.campaigns.store') }}" class="pg-card form-stack">@csrf
<div class="form-grid"><label>Campaign name<input name="name" required maxlength="160"></label><label>Classification<select name="message_type"><option value="marketing">Marketing / promotional</option><option value="operational">Operational member notice</option></select></label><label>Audience<select name="segment_id"><option value="">Default audience for classification</option>@foreach($segments as $segment)<option value="{{ $segment->id }}">{{ $segment->name }}</option>@endforeach</select></label><label>Template<select name="template_id"><option value="">No template</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }} · {{ ucfirst($template->message_type) }}</option>@endforeach</select></label><label>Schedule time<input type="datetime-local" name="scheduled_at"></label><label>Action<select name="action"><option value="draft">Save draft</option><option value="queue">Queue / schedule delivery</option></select></label></div>
<label>Email subject<input name="subject" maxlength="255" placeholder="Optional when the selected template supplies it"></label><label>Message<textarea name="body" rows="9" maxlength="30000" placeholder="Optional when the selected template supplies it"></textarea></label><button class="button button-primary">Save campaign</button></form>
</section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">HISTORY</span><h2>Campaign delivery history</h2><p>Sent, failed and skipped are based on recipient records. Delivered/opened/clicked remain zero unless a future verified provider or explicit tracking signal writes those events.</p></div></div>
<div class="pg-card" style="overflow-x:auto"><table class="pg-table"><thead><tr><th>Campaign</th><th>Type</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Skipped</th><th>Schedule / sent</th><th></th></tr></thead><tbody>
@forelse($campaigns as $campaign)<tr><td><strong>{{ $campaign->name }}</strong><br><small>{{ $campaign->subject }}</small></td><td>{{ ucfirst($campaign->message_type ?? 'marketing') }}</td><td><span class="pg-pill">{{ ucfirst($campaign->status) }}</span></td><td>{{ $campaign->recipient_total ?? 0 }}</td><td>{{ $campaign->sent_total ?? 0 }}</td><td>{{ $campaign->failed_total ?? 0 }}</td><td>{{ $campaign->skipped_total ?? 0 }}</td><td>@if($campaign->sent_at)Sent {{ \Carbon\Carbon::parse($campaign->sent_at)->format('M j, Y g:i A') }}@elseif($campaign->scheduled_at)Scheduled {{ \Carbon\Carbon::parse($campaign->scheduled_at)->format('M j, Y g:i A') }}@else—@endif</td><td>@if($campaign->status==='draft')<form method="post" action="{{ route('tenant.communications.campaigns.queue',$campaign->id) }}">@csrf<button class="button button-ghost">Queue</button></form>@endif</td></tr>@empty<tr><td colspan="9"><p class="muted">No campaigns yet.</p></td></tr>@endforelse
</tbody></table></div>
</section>
</div></section>
@endsection
