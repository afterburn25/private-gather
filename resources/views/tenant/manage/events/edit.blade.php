@extends('layouts.app')

@section('title', $event->exists ? 'Edit Event' : 'Create Event')

@section('content')
<section class="container section">
    <div class="eyebrow">LIFESTYLE EVENT BUILDER</div>
    <h1>{{ $event->exists ? 'Edit event' : 'Create event' }}</h1>

    <form
        method="post"
        class="panel form-card wide"
        action="{{ $event->exists ? route('tenant.events.update', $event) : route('tenant.events.store') }}"
    >
        @csrf
        @if($event->exists)
            @method('put')
        @endif

        <div class="form-grid">
            <label>
                Title
                <input name="title" value="{{ old('title', $event->title) }}" required>
            </label>

            <label>
                Slug
                <input name="slug" value="{{ old('slug', $event->slug) }}">
            </label>

            <label>
                Starts
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}" required>
            </label>

            <label>
                Ends
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}">
            </label>

            <label>
                Timezone
                <input name="timezone" value="{{ old('timezone', $event->timezone ?: 'America/Chicago') }}">
            </label>

            <label>
                Capacity
                <input type="number" name="capacity" value="{{ old('capacity', $event->capacity) }}">
            </label>

            <label>
                Visibility
                <select name="visibility">
                    @foreach(['public', 'members', 'unlisted', 'invite_only', 'private'] as $visibility)
                        <option value="{{ $visibility }}" @selected(old('visibility', $event->visibility ?: 'public') === $visibility)>
                            {{ ucfirst(str_replace('_', ' ', $visibility)) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                RSVP method
                <select name="rsvp_mode">
                    @foreach(['instant', 'approval', 'application', 'invite_only'] as $mode)
                        <option value="{{ $mode }}" @selected(old('rsvp_mode', $event->rsvp_mode ?: 'instant') === $mode)>
                            {{ ucfirst(str_replace('_', ' ', $mode)) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Status
                <select name="status">
                    @foreach(['draft', 'published', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $event->status ?: 'draft') === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Category
                <input name="category" value="{{ old('category', $event->category) }}" placeholder="Social, theme night, meet & greet">
            </label>

            <label>
                City
                <input name="city" value="{{ old('city', $event->city) }}">
            </label>

            <label>
                State / region
                <input name="region" value="{{ old('region', $event->region) }}">
            </label>

            <label class="span2">
                Public location label
                <input name="public_location_label" value="{{ old('public_location_label', $event->public_location_label) }}" placeholder="Chicago Area">
            </label>

            <label class="span2">
                Exact address
                <input name="exact_address" value="{{ old('exact_address', $event->exact_address) }}">
            </label>

            <label>
                Address visibility
                <select name="exact_address_visibility">
                    @foreach(['approved_attendees', 'organizer_only', 'public'] as $visibility)
                        <option value="{{ $visibility }}" @selected(old('exact_address_visibility', $event->exact_address_visibility ?: 'approved_attendees') === $visibility)>
                            {{ ucfirst(str_replace('_', ' ', $visibility)) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Dress code
                <input name="dress_code" value="{{ old('dress_code', $event->dress_code) }}" placeholder="Upscale, theme attire, club dress code">
            </label>

            <label>
                Registration opens
                <input type="datetime-local" name="registration_opens_at" value="{{ old('registration_opens_at', $event->registration_opens_at?->format('Y-m-d\TH:i')) }}">
            </label>

            <label>
                Registration closes
                <input type="datetime-local" name="registration_closes_at" value="{{ old('registration_closes_at', $event->registration_closes_at?->format('Y-m-d\TH:i')) }}">
            </label>

            <label>
                Repeat
                <select name="recurrence_rule">
                    <option value="">Do not repeat</option>
                    <option value="weekly" @selected($event->recurrence_rule === 'weekly')>Weekly</option>
                    <option value="monthly" @selected($event->recurrence_rule === 'monthly')>Monthly</option>
                </select>
            </label>

            <label>
                Repeat until
                <input type="date" name="recurrence_until" value="{{ $event->recurrence_until?->format('Y-m-d') }}">
            </label>

            <label class="span2">
                Summary
                <textarea name="summary">{{ old('summary', $event->summary) }}</textarea>
            </label>

            <label class="span2">
                Description
                <textarea rows="7" name="description">{{ old('description', $event->description) }}</textarea>
            </label>

            <label class="span2">
                Rules & consent expectations
                <textarea
                    rows="5"
                    name="rules"
                    placeholder="Consent, phones/photos, privacy, intoxication, conduct, prohibited behavior, and event-specific rules"
                >{{ old('rules', $event->rules) }}</textarea>
            </label>
        </div>

        <label class="check">
            <input type="checkbox" name="waitlist_enabled" value="1" @checked(old('waitlist_enabled', $event->exists ? $event->waitlist_enabled : true))>
            Enable automatic waitlist when full
        </label>

        <label class="check">
            <input type="checkbox" name="requires_verified_profile" value="1" @checked(old('requires_verified_profile', $event->requires_verified_profile))>
            Require a verified adult member/profile
        </label>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <button class="button button-primary">Save Event</button>
    </form>

    @if($event->exists)
        <div class="management-grid">
            <section class="panel">
                <h2>Lifestyle admission & ticket types</h2>
                <p class="muted">
                    Use profile eligibility for couple-only or individual-only admission. Member-only pricing and prior-approval requirements are enforced again at checkout.
                </p>

                @foreach($event->ticketTypes as $ticketType)
                    <div class="list-row">
                        <div>
                            <strong>{{ $ticketType->name }}</strong>
                            <small>
                                {{ $ticketType->price_cents ? '$'.number_format($ticketType->price_cents / 100, 2) : 'Free' }}
                                · {{ $ticketType->quantity ?? 'Unlimited' }} available
                                · {{ $ticketType->eligibilityLabel() }}
                                @if($ticketType->membership_required)
                                    · Members only
                                @endif
                                @if($ticketType->approval_required)
                                    · Approval required
                                @endif
                            </small>
                        </div>
                    </div>
                @endforeach

                <form class="form-stack" method="post" action="{{ route('tenant.ticket-types.store', $event) }}">
                    @csrf

                    <input name="name" placeholder="Ticket name — e.g. Couple Admission" required>
                    <textarea name="description" placeholder="Description"></textarea>

                    <label>
                        Eligible profile
                        <select name="profile_eligibility" required>
                            <option value="any">Any eligible profile</option>
                            <option value="couple">Couple profiles only</option>
                            <option value="individual">Individual profiles only</option>
                        </select>
                    </label>

                    <label class="check">
                        <input type="checkbox" name="membership_required" value="1">
                        Active club/organization membership required
                    </label>

                    <label class="check">
                        <input type="checkbox" name="approval_required" value="1">
                        Approved event attendance required before purchase
                    </label>

                    <div class="form-grid">
                        <input name="price" type="number" step="0.01" min="0" value="0" aria-label="Price">
                        <input name="quantity" type="number" min="1" placeholder="Quantity (blank unlimited)" aria-label="Quantity">
                        <input name="max_per_order" type="number" min="1" value="10" aria-label="Maximum per order">
                    </div>

                    <button>Add Ticket Type</button>
                </form>
            </section>

            <section class="panel">
                <h2>RSVP screening questions</h2>
                <p class="muted">
                    Use required questions for event-specific screening or acknowledgements. Membership approval remains a separate tenant-level process.
                </p>

                @foreach($questions as $question)
                    <div class="row-between">
                        <span>{{ $question->label }} · {{ $question->type }}</span>
                        <form method="post" action="{{ route('tenant.events.questions.destroy', [$event, $question->id]) }}">
                            @csrf
                            @method('delete')
                            <button class="link danger">Delete</button>
                        </form>
                    </div>
                @endforeach

                <form class="form-stack" method="post" action="{{ route('tenant.events.questions.store', $event) }}">
                    @csrf
                    <input name="label" placeholder="Question" required>
                    <select name="type">
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="select">Select</option>
                        <option value="checkbox">Checkbox</option>
                    </select>
                    <label class="check">
                        <input type="checkbox" name="required" value="1">
                        Required
                    </label>
                    <button>Add Question</button>
                </form>
            </section>
        </div>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Private invitations</h2>
                    <p>
                        Use invitations for invite-only/private lifestyle events. Accepting an invitation creates an approved attendee record before the protected event page or eligible ticket checkout becomes available.
                    </p>
                </div>
            </div>

            @if(session('event_invitation_url'))
                <div class="success">
                    <strong>Copy this invitation link now.</strong>
                    <p>For security, Private Gather stores only a one-way digest and cannot display this link again after you leave or refresh this page.</p>
                    <code>{{ session('event_invitation_url') }}</code>
                </div>
            @endif

            <form class="form-inline" method="post" action="{{ route('tenant.events.invitations.store', $event) }}">
                @csrf
                <label>
                    Recipient email (optional)
                    <input type="email" name="email" placeholder="member@example.com">
                </label>
                <label>
                    Guest allowance
                    <input type="number" name="max_guests" min="1" max="10" value="1" required>
                </label>
                <label>
                    Expires (optional)
                    <input type="datetime-local" name="expires_at">
                </label>
                <button>Create Invitation</button>
            </form>

            @forelse($invitations ?? [] as $invitation)
                <div class="domain-row">
                    <div>
                        <strong>{{ $invitation->email ?: 'Open invitation link' }}</strong>
                        <small>
                            {{ $invitation->status }} · up to {{ $invitation->max_guests }} guest(s)
                            @if($invitation->expires_at)
                                · expires {{ $invitation->expires_at->format('M j, Y g:i A') }}
                            @endif
                        </small>

                        @if($invitation->status === 'pending')
                            <small>Secure link hidden after creation. Revoke and create a new invitation if another link is needed.</small>
                        @endif
                    </div>

                    @if($invitation->status === 'pending')
                        <form method="post" action="{{ route('tenant.events.invitations.revoke', [$event, $invitation]) }}">
                            @csrf
                            @method('delete')
                            <button class="link danger">Revoke</button>
                        </form>
                    @endif
                </div>
            @empty
                <p>No invitations created yet.</p>
            @endforelse
        </section>

        <div class="row-actions">
            <form method="post" action="{{ route('tenant.events.duplicate', $event) }}">
                @csrf
                <button>Duplicate Event</button>
            </form>

            <form method="post" action="{{ route('tenant.events.destroy', $event) }}" onsubmit="return confirm('Delete this event?')">
                @csrf
                @method('delete')
                <button class="danger">Delete Event</button>
            </form>
        </div>
    @endif
</section>
@endsection
