@extends('layouts.app')
@section('title','Events — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell"><div class="row-between"><div><span class="pg-eyebrow">CLUB OS · EVENTS</span><h1>Calendar & events</h1><p>Build experiences, then manage admission, attendees and event operations from the same record.</p></div><a class="button button-primary" href="{{ route('tenant.events.create') }}">Create event</a></div></div></section>
<section class="pg-page"><div class="pg-shell"><div class="pg-grid">
@forelse($events as $event)
<article class="pg-card pg-card-interactive"><div class="row-between" style="gap:20px"><div><div class="pg-event-meta"><span class="pg-pill">{{ ucfirst($event->status) }}</span><span class="pg-pill">{{ ucfirst(str_replace('_',' ',$event->visibility)) }}</span><span class="pg-pill">{{ $event->starts_at->format('M j') }}</span></div><h2 style="margin:.7rem 0 .3rem">{{ $event->title }}</h2><p class="muted">{{ $event->starts_at->format('l, M j Y · g:i A') }} @if($event->public_location_label)· {{ $event->public_location_label }}@elseif($event->city)· {{ $event->city }}@endif</p></div><div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.events.attendees',$event) }}">Attendees</a><a class="button button-ghost" href="{{ route('tenant.events.edit',$event) }}">Edit</a></div></div></article>
@empty<div class="pg-empty"><h2>No events yet</h2><p>Create the first event for {{ $tenant->name }}.</p><a class="button button-primary" href="{{ route('tenant.events.create') }}">Create event</a></div>@endforelse
</div><div style="margin-top:30px">{{ $events->links() }}</div></div></section>
@endsection
