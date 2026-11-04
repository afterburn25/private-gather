@extends('layouts.app')
@section('title','Event Operations — '.$event->title)
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">EVENT OPERATIONS</span>
        <h1>{{ $event->title }}</h1>
        <p>This workspace is scoped to this event. Assigned event staff do not receive organization-wide Club OS permissions.</p>
        <div class="pg-actions">
            <a class="button button-ghost" href="{{ route('events.show',$event) }}">View event</a>
            @if($canManageEvent)
                <a class="button button-ghost" href="{{ route('tenant.events.edit',$event) }}">Event Studio</a>
            @endif
        </div>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="pg-grid pg-grid-3">
            @foreach($capabilities as $capability)
                <div class="pg-stat">
                    <span>Capability</span>
                    <strong>{{ str_replace('_',' ',ucfirst($capability)) }}</strong>
                </div>
            @endforeach
        </div>

        @if($canManageEvent)
            <section class="pg-section">
                <div class="pg-grid pg-grid-2">
                    <article class="pg-card form-stack">
                        <span class="pg-eyebrow">GUEST POLICY</span>
                        <h2>{{ $guestPolicyLabel }}</h2>
                        <p class="muted">Party size includes the account holder. Existing RSVPs are not rewritten when this policy changes.</p>
                        <form method="post" action="{{ route('tenant.events.guest-policy.update',$event) }}" class="form-stack">
                            @csrf
                            @method('PATCH')
                            <label>Guest policy
                                <select name="guest_policy">
                                    <option value="allowed" @selected($event->guest_policy==='allowed')>Guests allowed</option>
                                    <option value="approval_required" @selected($event->guest_policy==='approval_required')>Additional guests require approval</option>
                                    <option value="no_guests" @selected($event->guest_policy==='no_guests')>No additional guests</option>
                                </select>
                            </label>
                            <label>Maximum party size
                                <input name="max_party_size" type="number" min="1" max="10" required value="{{ $event->max_party_size ?: 10 }}">
                                <small>No-guests mode is always stored as party size 1.</small>
                            </label>
                            <button class="button button-primary">Save guest policy</button>
                        </form>
                    </article>

                    <article class="pg-card form-stack">
                        <span class="pg-eyebrow">EVENT STAFF</span>
                        <h2>Scoped event access</h2>
                        <p class="muted">Assign only the capabilities needed for this event. This never promotes the account to organization manager or staff.</p>
                        <form method="post" action="{{ route('tenant.events.staff.store',$event) }}" class="form-stack">
                            @csrf
                            <label>Account
                                <select name="user_id" required>
                                    <option value="">Select active organization account</option>
                                    @foreach($eventStaffCandidates as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->display_name ?: $candidate->name }} · {{ $candidate->email }} · {{ $candidate->role }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="form-grid">
                                <label class="row-actions"><input type="checkbox" name="capabilities[]" value="checkin"> Check-in / Door Mode</label>
                                <label class="row-actions"><input type="checkbox" name="capabilities[]" value="attendees"> Attendee list</label>
                                <label class="row-actions"><input type="checkbox" name="capabilities[]" value="communications"> Attendee communications</label>
                            </div>
                            <button class="button button-primary">Save event assignment</button>
                        </form>

                        <div class="form-stack">
                            @forelse($eventStaffAssignments as $assignment)
                                @php($assignmentCaps = json_decode((string) $assignment->capabilities, true) ?: [])
                                <div class="list-row">
                                    <div>
                                        <strong>{{ $assignment->display_name ?: $assignment->name }}</strong>
                                        <small>{{ $assignment->email }} · {{ implode(', ', $assignmentCaps) }} · {{ $assignment->status }}</small>
                                    </div>
                                    @if($assignment->status === 'active')
                                        <form method="post" action="{{ route('tenant.events.staff.deactivate',[$event,$assignment->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-ghost">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="muted">No event-scoped staff assignments yet.</p>
                            @endforelse
                        </div>
                    </article>
                </div>
            </section>

            <section class="pg-section">
                <div class="pg-section-head">
                    <div>
                        <span class="pg-eyebrow">PROMOS & COMPS</span>
                        <h2>Discount and complimentary admission codes</h2>
                        <p>Codes are event-scoped, redemption-limited and revalidated when payment completes. Deactivation never erases paid redemption history.</p>
                    </div>
                </div>
                <div class="pg-grid pg-grid-2">
                    <form method="post" action="{{ route('tenant.events.promos.store',$event) }}" class="pg-card form-stack">
                        @csrf
                        <div class="form-grid">
                            <label>Code<input name="code" maxlength="64" pattern="[A-Za-z0-9_-]+" required placeholder="VIP20"></label>
                            <label>Type
                                <select name="discount_kind">
                                    <option value="percent">Percentage off</option>
                                    <option value="fixed">Fixed dollar discount</option>
                                    <option value="comp">100% comp</option>
                                </select>
                            </label>
                            <label>Value<input name="discount_value" type="number" min="0" step="0.01" placeholder="20"><small>Percentage 1–100; fixed values are dollars; ignored for comp.</small></label>
                            <label>Maximum redemptions<input name="max_uses" type="number" min="1" max="1000000" placeholder="Unlimited"></label>
                            <label>Expires<input name="expires_at" type="datetime-local"></label>
                        </div>
                        <button class="button button-primary">Create promo / comp code</button>
                    </form>

                    <div class="pg-card">
                        <h3>Existing codes</h3>
                        @forelse($eventPromos as $promo)
                            <div class="list-row">
                                <div>
                                    <strong>{{ $promo->code }}</strong>
                                    <small>
                                        @if($promo->discount_type === 'percent')
                                            {{ $promo->discount_value }}% off
                                        @else
                                            ${{ number_format($promo->discount_value / 100, 2) }} off
                                        @endif
                                        · {{ $promo->uses }} used
                                        @if($promo->max_uses !== null)
                                            / {{ $promo->max_uses }} max
                                        @endif
                                        @if($promo->expires_at)
                                            · expires {{ \Carbon\Carbon::parse($promo->expires_at)->format('M j, Y g:i A') }}
                                        @endif
                                    </small>
                                </div>
                                <div>
                                    @if($promo->active)
                                        <form method="post" action="{{ route('tenant.events.promos.deactivate',[$event,$promo->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button button-ghost">Deactivate</button>
                                        </form>
                                    @else
                                        <span class="pg-pill">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="muted">No promo or comp codes for this event yet.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

        @if($capabilities->contains('checkin'))
            <section class="pg-section">
                <div class="pg-card">
                    <span class="pg-eyebrow">DOOR MODE</span>
                    <h2>Check-in</h2>
                    <p class="muted">Use the existing event check-in flow. Event-scoped access applies only to {{ $event->title }}.</p>
                    <a class="button button-primary" href="{{ route('tenant.checkin.index',$event) }}">Open check-in</a>
                </div>
            </section>
        @endif

        @if($capabilities->contains('attendees'))
            <section class="pg-section">
                <div class="pg-section-head">
                    <div>
                        <span class="pg-eyebrow">ATTENDEES</span>
                        <h2>Approved and pending RSVPs</h2>
                        <p>Party sizes are shown for event operations. This workspace does not expose unrelated member CRM notes or organization-wide records.</p>
                    </div>
                </div>
                <div class="pg-card">
                    @forelse($rsvps as $rsvp)
                        <div class="list-row">
                            <div>
                                <strong>{{ $rsvp->user?->display_name ?: $rsvp->user?->name ?: 'Member' }}</strong>
                                <small>{{ ucfirst($rsvp->status) }} · party of {{ $rsvp->guest_count }}</small>
                            </div>
                            <span class="pg-pill">{{ $rsvp->approved_at ? 'Approved '.$rsvp->approved_at->format('M j') : 'Awaiting review' }}</span>
                        </div>
                    @empty
                        <p class="muted">No active RSVPs yet.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if($capabilities->contains('communications'))
            <section class="pg-section">
                <div class="pg-section-head">
                    <div>
                        <span class="pg-eyebrow">ATTENDEE COMMUNICATIONS</span>
                        <h2>Operational event message</h2>
                        <p>Messages target currently approved attendees and are re-resolved at delivery time. They are operational notices, not marketing campaigns.</p>
                    </div>
                </div>
                <form method="post" action="{{ route('tenant.events.operations.message',$event) }}" class="pg-card form-stack">
                    @csrf
                    <label>Subject<input name="subject" maxlength="255" required value="{{ old('subject') }}" placeholder="Arrival update"></label>
                    <label>Message<textarea name="body" rows="7" maxlength="20000" required>{{ old('body') }}</textarea></label>
                    <div class="form-grid">
                        <label>Delivery
                            <select name="delivery">
                                <option value="now">Send now</option>
                                <option value="after_event">Post-event follow-up · 2 hours after end</option>
                                <option value="custom">Schedule a time</option>
                            </select>
                        </label>
                        <label>Custom send time<input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}"></label>
                    </div>
                    <div class="pg-privacy-note">Private venue credentials, door codes and other secrets should not be placed in broad email notices.</div>
                    <button class="button button-primary">Queue attendee message</button>
                </form>
            </section>
        @endif
    </div>
</section>
@endsection
