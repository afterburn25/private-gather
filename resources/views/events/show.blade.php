@extends('layouts.app')

@section('title', $event->title)

@section('content')
@php
    $viewer = auth()->user();
    $viewerProfileType = $viewer?->profile?->profile_type;
    $viewerIsMember = $viewer
        ? \App\Support\TenantMembership::hasActiveMembership($viewer, (int) $event->tenant_id)
        : false;
    $viewerApproved = $viewer
        ? $event->rsvps()->where('user_id', $viewer->id)->where('status', 'approved')->exists()
        : false;
@endphp

<section class="event-hero">
    <div class="container">
        <div class="eyebrow">{{ strtoupper($event->category ?: 'LIFESTYLE EVENT') }}</div>
        <h1>{{ $event->title }}</h1>
        <p>{{ $event->summary }}</p>

        <div class="event-meta">
            <span>{{ $event->starts_at->format('l, F j · g:i A') }}</span>
            <span>{{ $event->public_location_label ?: trim($event->city.', '.$event->region, ', ') }}</span>
            <span>Hosted by {{ $event->tenant->name }}</span>
        </div>

        @auth
            <form method="post" action="{{ route('rsvp.store', $event) }}" class="rsvp-box">
                @csrf

                <label>
                    Guests
                    <input type="number" name="guest_count" min="1" max="10" value="1">
                </label>

                @foreach($questions as $q)
                    <label>
                        {{ $q->label }}

                        @if($q->type === 'textarea')
                            <textarea name="answers[{{ $q->id }}]" @required($q->required)></textarea>
                        @elseif($q->type === 'checkbox')
                            <input type="checkbox" name="answers[{{ $q->id }}]" value="1" @required($q->required)>
                        @else
                            <input name="answers[{{ $q->id }}]" @required($q->required)>
                        @endif
                    </label>
                @endforeach

                <button class="button button-primary">
                    {{ $event->rsvp_mode === 'instant' ? 'RSVP Now' : 'Request RSVP' }}
                </button>

                @if($remaining !== null)
                    <small>{{ $remaining }} spots currently available</small>
                @endif
            </form>
        @else
            <a class="button button-primary" href="{{ route('login') }}">Log in to RSVP</a>
        @endauth
    </div>
</section>

<section class="container section two-col">
    <article>
        <h2>About this event</h2>
        <div class="prose">{!! nl2br(e($event->description)) !!}</div>

        @if($event->dress_code)
            <h2>Dress code</h2>
            <p>{{ $event->dress_code }}</p>
        @endif

        @if($event->rules)
            <h2>Rules & consent</h2>
            <div class="prose">{!! nl2br(e($event->rules)) !!}</div>
        @endif

        @if($event->ticketTypes->isNotEmpty())
            <h2>Admission & tickets</h2>

            <div class="ticket-list">
                @foreach($event->ticketTypes as $type)
                    @php
                        $eligibilityReason = null;

                        if ($viewer) {
                            if ($type->profile_eligibility !== 'any' && $viewerProfileType !== $type->profile_eligibility) {
                                $eligibilityReason = $type->profile_eligibility === 'couple'
                                    ? 'Couple profile required'
                                    : 'Individual profile required';
                            } elseif ($type->membership_required && ! $viewerIsMember) {
                                $eligibilityReason = 'Active membership required';
                            } elseif ($type->approval_required && ! $viewerApproved) {
                                $eligibilityReason = 'Event approval required';
                            }
                        }
                    @endphp

                    <div class="panel row-between">
                        <div>
                            <strong>{{ $type->name }}</strong>
                            <small>
                                {{ $type->description }} · {{ $type->price_cents ? '$'.number_format($type->price_cents / 100, 2) : 'Free' }}
                            </small>
                            <small>
                                {{ $type->eligibilityLabel() }}
                                @if($type->membership_required)
                                    · Members only
                                @endif
                                @if($type->approval_required)
                                    · Prior approval required
                                @endif
                            </small>
                        </div>

                        @auth
                            @if($eligibilityReason)
                                <span class="muted">{{ $eligibilityReason }}</span>
                            @else
                                <form method="post" action="{{ route('checkout.store', [$event, $type]) }}" class="inline-controls">
                                    @csrf
                                    <input name="quantity" type="number" min="1" max="{{ $type->max_per_order }}" value="1">
                                    <button>Get Tickets</button>
                                </form>
                            @endif
                        @else
                            <a href="{{ route('login') }}">Log in</a>
                        @endauth
                    </div>
                @endforeach
            </div>
        @endif
    </article>

    <aside class="panel event-aside">
        <h3>Event details</h3>
        <p><strong>Date</strong><br>{{ $event->starts_at->format('M j, Y g:i A') }}</p>
        <p><strong>Location</strong><br>{{ $event->public_location_label ?: $event->city }}</p>

        @if($event->exact_address_visibility === 'public' && $event->exact_address)
            <p><strong>Address</strong><br>{{ $event->exact_address }}</p>
        @elseif($event->exact_address_visibility === 'approved_attendees' && $viewerApproved && $event->exact_address)
            <p><strong>Approved location</strong><br>{{ $event->exact_address }}</p>
        @endif
    </aside>
</section>
@endsection
