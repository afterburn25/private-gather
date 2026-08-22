@extends('layouts.app')
@section('title','Analytics')
@section('content')
<div class="container">
    <div class="title-row">
        <div><h1>Analytics</h1><p>Since {{ $from->format('M j, Y') }}</p></div>
        <a class="button secondary" href="{{ route('tenant.analytics.export') }}">Export CSV</a>
    </div>

    @if(isset($availability) && in_array(false, $availability, true))
        <div class="notice">Analytics is available, but one or more legacy reporting tables are not initialized yet. Available metrics are shown as normal and unavailable metrics are shown as zero instead of failing the page.</div>
    @endif

    <div class="stats">
        <div class="stat"><strong>{{ $stats['event_views'] }}</strong><span>Event views</span></div>
        <div class="stat"><strong>{{ $stats['rsvps'] }}</strong><span>RSVPs</span></div>
        <div class="stat"><strong>{{ $stats['checkins'] }}</strong><span>Check-ins</span></div>
        <div class="stat"><strong>{{ $stats['orders'] }}</strong><span>Orders</span></div>
        <div class="stat"><strong>${{ number_format($stats['gross_cents']/100, 2) }}</strong><span>Gross sales</span></div>
    </div>

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><div><span class="eyebrow">COMMUNITY HEALTH</span><h2>Member engagement</h2></div><span class="muted">Privacy-safe aggregate counts only</span></div>
        <div class="stats"><div class="stat"><strong>{{ $stats['active_members'] }}</strong><span>Active members</span></div><div class="stat"><strong>{{ $stats['community_posts'] }}</strong><span>Wall posts</span></div><div class="stat"><strong>{{ $stats['comments'] }}</strong><span>Comments</span></div><div class="stat"><strong>{{ $stats['messages'] }}</strong><span>Messages sent</span></div><div class="stat"><strong>{{ $stats['groups'] }}</strong><span>Groups</span></div><div class="stat"><strong>{{ $stats['gallery_media'] }}</strong><span>Gallery media</span></div></div>
        <p class="muted">Private message bodies, private gallery contents and individual browsing activity are never exposed in club analytics.</p>
    </div>

    <div class="panel">
        <h2>Event performance</h2>
        @forelse($events as $event)
            <div class="row-between"><span>{{ $event->title }}</span><strong>{{ (int) ($event->rsvps_count ?? 0) }} RSVPs</strong></div>
        @empty
            <div class="empty-state">No event analytics yet.</div>
        @endforelse
    </div>
</div>
@endsection
