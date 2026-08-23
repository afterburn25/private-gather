@extends('layouts.app')
@section('title','Discover Events — '.config('app.name'))
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">DISCOVER</span>
        <h1>Find the right event.</h1>
        <p>Search public and discoverable lifestyle events without exposing protected private-location details.</p>
        <form class="pg-card" method="get" action="{{ route('site.events') }}" style="margin-top:24px">
            <div class="form-grid">
                <label>What are you looking for?<input name="q" value="{{ request('q') }}" placeholder="Party, social, takeover, pool night"></label>
                <label>City or metro<input name="city" value="{{ request('city') }}" placeholder="City"></label>
                <label>Category<input name="category" value="{{ request('category') }}" placeholder="Event type"></label>
                <label style="align-self:end"><button class="button button-primary" style="width:100%">Search events</button></label>
            </div>
            <div class="pg-trust-row"><span class="pg-pill">Exact addresses can stay private</span><span class="pg-pill">Hosts control admission</span><span class="pg-pill">18+ communities</span></div>
        </form>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell">
        <div class="pg-section-head">
            <div><span class="pg-eyebrow">UPCOMING</span><h2>{{ $events->total() }} {{ $events->total() === 1 ? 'event' : 'events' }}</h2><p>Results reflect each host's visibility settings.</p></div>
        </div>
        <div class="pg-grid pg-grid-3">
            @forelse($events as $event)
                <article class="pg-card pg-card-interactive pg-event-card">
                    <div class="pg-media"><div class="pg-media-overlay"><span class="pg-pill">{{ $event->starts_at->format('M j') }}</span></div></div>
                    <div class="pg-event-body">
                        <div class="pg-event-meta">
                            @if($event->category)<span class="pg-pill">{{ $event->category }}</span>@endif
                            <span class="pg-pill">{{ $event->public_location_label ?: $event->city ?: 'Location protected' }}</span>
                        </div>
                        <h3 class="pg-event-title">{{ $event->title }}</h3>
                        <p class="pg-event-copy">{{ $event->starts_at->format('l, M j · g:i A') }}<br>Hosted by {{ $event->tenant->name }}</p>
                        @if($event->summary)<p class="muted">{{ \Illuminate\Support\Str::limit($event->summary, 120) }}</p>@endif
                        <a class="text-link" href="{{ route('events.show', $event) }}">View event →</a>
                    </div>
                </article>
            @empty
                <div class="pg-empty"><h2>No matching events</h2><p>Try a broader keyword or city. Private/invite-only events only appear when their host allows them to.</p><a class="button button-ghost" href="{{ route('site.events') }}">Clear filters</a></div>
            @endforelse
        </div>
        <div style="margin-top:30px">{{ $events->links() }}</div>
    </div>
</section>
@endsection
