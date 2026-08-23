@php
    $tenantContext = app(\App\Tenancy\TenantContext::class);
    $isTenantSite = $tenantContext->check();
    $tenant = $isTenantSite ? $tenantContext->requireTenant()->load('branding') : $event->tenant;
    $publicShell = $isTenantSite ? 'tenant-shell' : 'pg-shell';
@endphp
@extends($isTenantSite ? 'layouts.tenant-site' : 'layouts.app')
@section('title', $event->title)
@section('content')
@php
    $viewer = auth()->user();
    $viewerProfileType = $viewer?->profile?->profile_type;
    $viewerIsMember = $viewer ? \App\Support\TenantMembership::hasActiveMembership($viewer, (int) $event->tenant_id) : false;
    $viewerApproved = $viewer ? $event->rsvps()->where('user_id', $viewer->id)->where('status', 'approved')->exists() : false;
    $publicLocation = $event->public_location_label ?: trim($event->city.', '.$event->region, ', ');
@endphp

<section class="pg-page-hero {{ $isTenantSite ? 'tenant-site-hero' : '' }}">
    <div class="{{ $publicShell }} pg-grid pg-grid-2" style="align-items:end">
        <div>
            <div class="pg-event-meta">
                <span class="pg-pill">{{ strtoupper($event->category ?: 'Lifestyle event') }}</span>
                <span class="pg-pill">{{ $event->starts_at->format('M j') }}</span>
                <span class="pg-pill">{{ $publicLocation ?: 'Location protected' }}</span>
            </div>
            <h1>{{ $event->title }}</h1>
            <p>{{ $event->summary }}</p>
            <div class="pg-trust-row">
                <span class="pg-pill">Hosted by {{ $event->tenant->name }}</span>
                @if($event->rsvp_mode === 'instant')<span class="pg-pill pg-pill-good">Instant RSVP</span>@else<span class="pg-pill pg-pill-warn">Approval required</span>@endif
                @if($remaining !== null)<span class="pg-pill">{{ $remaining }} spots available</span>@endif
            </div>
        </div>
        <div class="pg-media"><div class="pg-media-overlay"><span class="pg-eyebrow">{{ $event->starts_at->format('l, F j') }}</span><h2 style="margin:.35rem 0 0">{{ $event->starts_at->format('g:i A') }}</h2></div></div>
    </div>
</section>

<section class="pg-page">
    <div class="{{ $publicShell }} pg-grid" style="grid-template-columns:minmax(0,1.5fr) minmax(300px,.7fr);align-items:start">
        <div class="pg-grid">
            <article class="pg-card">
                <span class="pg-eyebrow">THE EXPERIENCE</span>
                <h2>About this event</h2>
                <div class="prose">{!! nl2br(e($event->description)) !!}</div>
            </article>

            @if($event->dress_code || $event->rules)
                <div class="pg-grid pg-grid-2">
                    @if($event->dress_code)
                        <article class="pg-card"><span class="pg-eyebrow">ARRIVE READY</span><h2>Dress code</h2><p>{{ $event->dress_code }}</p></article>
                    @endif
                    @if($event->rules)
                        <article class="pg-card"><span class="pg-eyebrow">CONSENT & CULTURE</span><h2>House rules</h2><div class="prose">{!! nl2br(e($event->rules)) !!}</div></article>
                    @endif
                </div>
            @endif

            @if($event->ticketTypes->isNotEmpty())
                <section>
                    <div class="pg-section-head"><div><span class="pg-eyebrow">ADMISSION</span><h2>Tickets & access</h2><p>Ticket eligibility is enforced before purchase.</p></div></div>
                    <div class="pg-grid">
                        @foreach($event->ticketTypes as $type)
                            @php
                                $eligibilityReason = null;
                                if ($viewer) {
                                    if ($type->profile_eligibility !== 'any' && $viewerProfileType !== $type->profile_eligibility) {
                                        $eligibilityReason = $type->profile_eligibility === 'couple' ? 'Couple profile required' : 'Individual profile required';
                                    } elseif ($type->membership_required && ! $viewerIsMember) {
                                        $eligibilityReason = 'Active membership required';
                                    } elseif ($type->approval_required && ! $viewerApproved) {
                                        $eligibilityReason = 'Event approval required';
                                    }
                                }
                            @endphp
                            <article class="pg-card">
                                <div class="row-between">
                                    <div>
                                        <div class="pg-event-meta"><span class="pg-pill">{{ $type->eligibilityLabel() }}</span>@if($type->membership_required)<span class="pg-pill">Members only</span>@endif @if($type->approval_required)<span class="pg-pill">Approval required</span>@endif</div>
                                        <h3 style="margin:.6rem 0 .25rem">{{ $type->name }}</h3>
                                        <p class="muted">{{ $type->description }}</p>
                                    </div>
                                    <strong style="font-size:1.5rem">{{ $type->price_cents ? '$'.number_format($type->price_cents / 100, 2) : 'Free' }}</strong>
                                </div>
                                <div class="pg-actions" style="margin-top:16px">
                                    @auth
                                        @if($eligibilityReason)
                                            <span class="pg-pill pg-pill-warn">{{ $eligibilityReason }}</span>
                                        @else
                                            <form method="post" action="{{ route('checkout.store', [$event, $type]) }}" class="inline-controls">
                                                @csrf
                                                <label class="pg-sr-only" for="qty-{{ $type->id }}">Quantity</label>
                                                <input id="qty-{{ $type->id }}" name="quantity" type="number" min="1" max="{{ $type->max_per_order }}" value="1" style="width:84px">
                                                <button class="button button-primary">Get tickets</button>
                                            </form>
                                        @endif
                                    @else
                                        <a class="button button-primary" href="{{ route('login') }}">Log in for tickets</a>
                                    @endauth
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="pg-grid" style="position:sticky;top:140px">
            <section class="pg-card">
                <span class="pg-eyebrow">YOUR RSVP</span>
                <h2 style="margin:.45rem 0 1rem">Join the guest list</h2>
                @auth
                    <form method="post" action="{{ route('rsvp.store', $event) }}" class="form-stack">
                        @csrf
                        <label>Guests<input type="number" name="guest_count" min="1" max="10" value="1"></label>
                        @foreach($questions as $q)
                            <label>{{ $q->label }}
                                @if($q->type === 'textarea')
                                    <textarea name="answers[{{ $q->id }}]" rows="4" @required($q->required)></textarea>
                                @elseif($q->type === 'checkbox')
                                    <span class="row-actions"><input type="checkbox" name="answers[{{ $q->id }}]" value="1" @required($q->required)> Confirm</span>
                                @else
                                    <input name="answers[{{ $q->id }}]" @required($q->required)>
                                @endif
                            </label>
                        @endforeach
                        <button class="button button-primary">{{ $event->rsvp_mode === 'instant' ? 'RSVP now' : 'Request approval' }}</button>
                    </form>
                @else
                    <p class="muted">Log in to RSVP while keeping your account and community activity connected.</p>
                    <a class="button button-primary" href="{{ route('login') }}">Log in to RSVP</a>
                @endauth
            </section>

            <section class="pg-card">
                <span class="pg-eyebrow">DETAILS</span>
                <h3>Date & time</h3><p>{{ $event->starts_at->format('M j, Y · g:i A') }}</p>
                <h3>Location</h3><p>{{ $publicLocation ?: 'Shared according to the host’s privacy settings.' }}</p>
                @if($event->exact_address_visibility === 'public' && $event->exact_address)
                    <p><strong>Address</strong><br>{{ $event->exact_address }}</p>
                @elseif($event->exact_address_visibility === 'approved_attendees' && $viewerApproved && $event->exact_address)
                    <p><strong>Approved attendee location</strong><br>{{ $event->exact_address }}</p>
                @else
                    <div class="pg-privacy-note">Exact private locations are not exposed before the host’s configured access condition is met.</div>
                @endif
            </section>
        </aside>
    </div>
</section>
@endsection
