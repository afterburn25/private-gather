@extends('layouts.tenant-site')
@section('title','Membership Invitation — '.$tenant->name)
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">PRIVATE MEMBERSHIP INVITATION</span>
        <h1>You’ve been invited to join {{ $tenant->name }}.</h1>
        <p>This invitation is bound to your signed-in Private Gather account and this organization. It cannot grant staff or operator permissions.</p>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell" style="max-width:760px">
        <div class="pg-card form-stack">
            <span class="pg-pill">{{ $invitation->level?->name ?? 'Membership' }}</span>
            <h2>Review your invitation</h2>
            <p>{{ $invitation->message ?: 'The organization has invited you to activate membership.' }}</p>

            <div class="pg-grid pg-grid-2">
                <div>
                    <strong>Invited account</strong>
                    <p class="muted">{{ $invitation->email }}</p>
                </div>
                <div>
                    <strong>Expires</strong>
                    <p class="muted">{{ $invitation->expires_at?->format('M j, Y · g:i A') }}</p>
                </div>
            </div>

            @if($invitation->level)
                <div class="pg-privacy-note">
                    <strong>{{ $invitation->level->name }}</strong>
                    · {{ $invitation->level->priceLabel() }}
                    · {{ ucfirst($invitation->level->billing_interval) }}
                    @if($invitation->level->grace_days)
                        · {{ $invitation->level->grace_days }} day grace period
                    @endif
                </div>
            @endif

            <form method="post" action="{{ route('membership-invitations.accept',$token) }}" class="form-stack">
                @csrf
                <label class="row-actions">
                    <input type="checkbox" name="rules_ack" value="1" required>
                    I agree to follow this organization’s rules and consent standards.
                </label>
                <label class="row-actions">
                    <input type="checkbox" name="privacy_ack" value="1" required>
                    I understand this organization’s privacy and discretion expectations.
                </label>
                <button class="button button-primary">Accept membership invitation</button>
            </form>

            <div class="pg-privacy-note">Accepting activates organization membership only. Your global Private Gather profile, privacy settings, connections and memberships in other organizations remain separate.</div>
        </div>
    </div>
</section>
@endsection
