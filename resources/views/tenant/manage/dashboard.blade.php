@extends('layouts.app')
@section('title','Club OS — '.$tenant->name)
@section('content')
@php
$primary=$tenant->domains->firstWhere('is_primary',true);
$siteUrl=$primary?\App\Support\TenantUrl::to($primary):\App\Support\MountUrl::to('/');
$isHosted=\App\Support\Edition::isHosted();
$readiness=$overview['readiness'];
$metrics=$overview['metrics'];
@endphp
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">{{ $isHosted ? 'CLUB OS' : 'PRIVATE COMMUNITY OPERATIONS' }}</span>
<h1>{{ $tenant->name }}</h1>
<p>{{ $isHosted ? 'Run members, guests, events, admission, tickets, commerce, communications, staff, CRM and your branded website from one operator workspace.' : 'Manage this private installation’s website, events, memberships, members and staff from one workspace.' }}</p>
<div class="pg-actions" style="margin-top:22px">@if($siteUrl)<a class="button button-primary" target="_blank" rel="noopener" href="{{ $siteUrl }}">Open {{ $isHosted ? 'club site' : 'site' }}</a>@endif @if($isHosted)<a class="button button-ghost" target="_blank" rel="noopener" href="{{ route('organizations.index',['preview'=>$tenant->id]) }}">Preview Website</a><a class="button button-ghost" href="{{ route('organizations.index') }}">Switch workspace</a>@endif</div>
@if($siteUrl)<p class="muted" style="margin-top:14px">Site address: <a class="text-link" target="_blank" rel="noopener" href="{{ $siteUrl }}">{{ $siteUrl }}</a></p>@endif
</div></section>
<section class="pg-page"><div class="pg-shell">
<div class="pg-grid pg-grid-4"><div class="pg-stat"><span>Active Members</span><strong>{{ $metrics['active_members'] }}</strong></div><div class="pg-stat"><span>Upcoming Events</span><strong>{{ $metrics['upcoming_events'] }}</strong></div>@if($isHosted)<div class="pg-stat"><span>Pending Applications</span><strong>{{ $metrics['pending_applications'] }}</strong></div>@else<div class="pg-stat"><span>Events</span><strong>{{ $tenant->events_count }}</strong></div>@endif<div class="pg-stat"><span>Scheduled Notices</span><strong>{{ $metrics['scheduled_announcements'] }}</strong></div></div>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">OPERATING HEALTH</span><h2>Know what needs attention</h2><p>Readiness is calculated from live configuration and content, not from a manually checked checklist.</p></div><span class="pg-pill {{ $readiness['percent'] === 100 ? '' : 'pg-pill-warn' }}">{{ $readiness['percent'] }}% ready</span></div>
<div class="pg-grid pg-grid-2">
<article class="pg-card form-stack"><div class="row-between"><div><span class="pg-eyebrow">READINESS</span><h2>{{ $readiness['completed'] }} of {{ $readiness['total'] }} core checks complete</h2></div><strong style="font-size:2.4rem">{{ $readiness['percent'] }}%</strong></div>
<div class="form-stack">@foreach($readiness['checks'] as $check)<div class="list-row"><div><strong>{{ $check['complete'] ? '✓' : '○' }} {{ $check['label'] }}</strong>@if(!$check['complete'])<small>{{ $check['action'] }}</small>@endif</div>@if(!$check['complete'] && \Illuminate\Support\Facades\Route::has($check['route']))<a class="button button-ghost" href="{{ route($check['route']) }}">Fix</a>@endif</div>@endforeach</div>
@if($readiness['percent']===100)<div class="success" role="status">Core launch readiness is complete. Keep monitoring event, domain, member and communication health as the community changes.</div>@else<div class="pg-privacy-note">{{ $readiness['next_actions']->count() }} core action{{ $readiness['next_actions']->count()===1?'':'s' }} remain before this workspace reaches full launch readiness.</div>@endif
</article>
<article class="pg-card form-stack"><span class="pg-eyebrow">LIVE PULSE</span><h2>Today in {{ $tenant->name }}</h2><div class="pg-grid pg-grid-2"><div class="pg-stat"><span>Active members</span><strong>{{ $metrics['active_members'] }}</strong></div><div class="pg-stat"><span>Upcoming events</span><strong>{{ $metrics['upcoming_events'] }}</strong></div><div class="pg-stat"><span>Pending applications</span><strong>{{ $metrics['pending_applications'] }}</strong></div><div class="pg-stat"><span>Scheduled notices</span><strong>{{ $metrics['scheduled_announcements'] }}</strong></div></div>@if($overview['domain_warning'])<div class="error" role="alert"><strong>Domain attention</strong><p>{{ $overview['domain_warning'] }}</p><a class="text-link" href="{{ route('tenant.domains.index') }}">Review domains →</a></div>@else<div class="success" role="status">Primary domain health has no current readiness warning.</div>@endif</article>
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">WHAT'S HAPPENING</span><h2>Events, communications & recent changes</h2><p>See the next operational items without opening several modules.</p></div></div><div class="pg-grid pg-grid-3">
<article class="pg-card"><div class="row-between"><div><span class="pg-eyebrow">UPCOMING</span><h3>Events</h3></div><a class="text-link" href="{{ route('tenant.events.index') }}">All events →</a></div>@forelse($overview['upcoming_events'] as $event)<div class="list-row"><div><strong>{{ $event->title }}</strong><small>{{ $event->starts_at?->format('M j · g:i A') }}</small></div><a class="button button-ghost" href="{{ route('tenant.events.edit',$event) }}">Manage</a></div>@empty<p class="muted">No published upcoming events yet.</p>@endforelse</article>
<article class="pg-card"><div class="row-between"><div><span class="pg-eyebrow">COMMUNICATIONS</span><h3>Recent notices</h3></div>@if(\Illuminate\Support\Facades\Route::has('tenant.announcements.index'))<a class="text-link" href="{{ route('tenant.announcements.index') }}">Announcements →</a>@endif</div>@forelse($overview['recent_announcements'] as $announcement)<div class="list-row"><div><strong>{{ $announcement->title }}</strong><small>{{ ucfirst($announcement->status) }} · {{ str_replace('_',' ',ucfirst($announcement->audience)) }}</small></div></div>@empty<p class="muted">No published or scheduled announcements yet.</p>@endforelse</article>
<article class="pg-card"><span class="pg-eyebrow">AUDIT TRAIL</span><h3>Recent operator activity</h3>@forelse($overview['recent_activity'] as $activity)<div class="list-row"><div><strong>{{ str_replace(['.','_'],' ',ucwords($activity->action,'. _')) }}</strong><small>{{ $activity->subject_type ?: 'Tenant' }} · {{ $activity->created_at?->diffForHumans() }}</small></div></div>@empty<p class="muted">No audited operator changes have been recorded yet.</p>@endforelse</article>
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">OPERATIONS</span><h2>Run the community</h2><p>Daily member, guest, event and admission work stays up front.</p></div></div><div class="pg-grid pg-grid-4">
<a class="pg-card pg-card-interactive" href="{{ route('tenant.events.index') }}"><span class="pg-pill">Calendar</span><h3>Events & Door Mode</h3><p class="muted">Create experiences, manage capacity, attendees, screening, invitations, waitlists, tickets and launch check-in from each event.</p></a>
@if($isHosted)<a class="pg-card pg-card-interactive" href="{{ route('tenant.membership-applications.index') }}"><span class="pg-pill pg-pill-warn">{{ $tenant->pending_membership_applications_count }} pending</span><h3>Members & applications</h3><p class="muted">Review prospective members without changing their global account.</p></a>@else<a class="pg-card pg-card-interactive" href="{{ route('tenant.members.index') }}"><span class="pg-pill">Membership</span><h3>Members & approvals</h3><p class="muted">Manage who belongs to this installation.</p></a>@endif
<a class="pg-card pg-card-interactive" href="{{ route('tenant.membership-levels.index') }}"><span class="pg-pill">Memberships</span><h3>Levels, terms & renewals</h3><p class="muted">Standard, VIP, annual, lifetime, couple and individual membership tiers.</p></a>
@if(\Illuminate\Support\Facades\Route::has('tenant.crm.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.crm.index') }}"><span class="pg-pill">CRM</span><h3>Members, guests & CRM</h3><p class="muted">Segment contacts with tags and keep private operator notes.</p></a>@endif
<a class="pg-card pg-card-interactive" href="{{ route('tenant.orders.index') }}"><span class="pg-pill">Tickets</span><h3>Tickets & orders</h3><p class="muted">Track event orders, ticket state and payment history.</p></a>
@if(\Illuminate\Support\Facades\Route::has('tenant.commerce.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.commerce.index') }}"><span class="pg-pill">Commerce</span><h3>Payments & payouts</h3><p class="muted">Gross, platform fees, net, availability, merchant state, refunds and membership subscriptions.</p></a>@endif
<a class="pg-card pg-card-interactive" href="{{ route('tenant.staff.index') }}"><span class="pg-pill">Team</span><h3>Staff & permissions</h3><p class="muted">Role-scoped operational access for managers, staff and door/check-in teams.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.badges.index') }}"><span class="pg-pill">Recognition</span><h3>Club badges</h3><p class="muted">Create, assign and revoke club-issued recognition.</p></a>
@if($tenant->isOrganization() && \Illuminate\Support\Facades\Route::has('tenant.chapters.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.chapters.index') }}"><span class="pg-pill">Structure</span><h3>Chapters & local groups</h3><p class="muted">Run city and regional chapters using the same organization membership, group discussions and privacy boundary.</p></a>@endif
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">COMMUNICATIONS & GROWTH</span><h2>Keep people connected</h2><p>Member communications and consent-aware growth tools belong in the operator workspace.</p></div></div><div class="pg-grid pg-grid-4">
@if(\Illuminate\Support\Facades\Route::has('tenant.announcements.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.announcements.index') }}"><span class="pg-pill">Notices</span><h3>Announcements</h3><p class="muted">Publish tenant-scoped operational notices to active members, staff or approved event attendees with an auditable delivery snapshot.</p></a>@endif
@if(\Illuminate\Support\Facades\Route::has('tenant.growth.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.growth.index') }}"><span class="pg-pill">Communications</span><h3>Growth & campaigns</h3><p class="muted">Referrals, consented contacts and campaign workflows without bypassing communication preferences.</p></a>@endif
<a class="pg-card pg-card-interactive" href="{{ route('messages.index') }}"><span class="pg-pill">Messages</span><h3>Operator conversations</h3><p class="muted">Use your member account for private conversations while organization data remains tenant-scoped.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.analytics.index') }}"><span class="pg-pill">Reports</span><h3>Analytics & exports</h3><p class="muted">Understand events, admission and operational activity with export support.</p></a>
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">WEBSITE & BRAND</span><h2>Own the club experience</h2><p>Subdomain, custom domain, visual identity, website builder and media are managed together.</p></div></div><div class="pg-grid pg-grid-4">
@if(\Illuminate\Support\Facades\Route::has('tenant.builder.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.builder.index') }}"><h3>Website Builder 2.0</h3><p class="muted">Template-driven site composition with accessible section ordering.</p></a>@endif
<a class="pg-card pg-card-interactive" href="{{ route('tenant.cms.pages') }}"><h3>Pages & CMS</h3><p class="muted">Club mini-site pages and reusable content sections.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.branding.edit') }}"><h3>Branding & theme</h3><p class="muted">Logo, colors, font and tenant theme presentation.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.media.index') }}"><h3>Media library</h3><p class="muted">Reusable local website and event media.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.navigation.index') }}"><h3>Navigation</h3><p class="muted">Control the public visitor journey.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.site-settings.edit') }}"><h3>Site settings</h3><p class="muted">Community website configuration.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.domains.index') }}"><h3>Existing domains</h3><p class="muted">Hosted subdomains and custom domain verification.</p></a>
@if(\Illuminate\Support\Facades\Route::has('tenant.domain-marketplace.index'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.domain-marketplace.index') }}"><h3>Find a domain</h3><p class="muted">Availability and registrar workflow when a provider is configured.</p></a>@endif
</div></section>

<section class="pg-section"><div class="pg-section-head"><div><span class="pg-eyebrow">CONTROL</span><h2>Settings and readiness</h2><p>Keep operational and public configuration explicit.</p></div></div><div class="pg-grid pg-grid-4">
@if(\Illuminate\Support\Facades\Route::has('tenant.privacy-access.edit'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.privacy-access.edit') }}"><span class="pg-pill">Policy</span><h3>Privacy & Access</h3><p class="muted">Membership eligibility, event defaults, network discovery, member directory and attendance visibility.</p></a>@endif
@if(\Illuminate\Support\Facades\Route::has('tenant.onboarding'))<a class="pg-card pg-card-interactive" href="{{ route('tenant.onboarding') }}"><h3>Operator onboarding</h3><p class="muted">Review the organization setup checklist and readiness state.</p></a>@endif
<a class="pg-card pg-card-interactive" href="{{ route('tenant.analytics.index') }}"><h3>Reports</h3><p class="muted">Operational reporting and export.</p></a>
<a class="pg-card pg-card-interactive" href="{{ route('tenant.site-settings.edit') }}"><h3>Settings</h3><p class="muted">Website and community configuration.</p></a>
</div></section>
</div></section>
@endsection