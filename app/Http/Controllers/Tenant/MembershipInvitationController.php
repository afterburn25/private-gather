<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Mail\MembershipInvitationMail;
use App\Models\TenantMembershipInvitation;
use App\Models\TenantMembershipLevel;
use App\Models\User;
use App\Services\MembershipLevelService;
use App\Services\TenantMembershipAccessService;
use App\Support\Audit;
use App\Support\TenantMembership;
use App\Support\TenantUrl;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MembershipInvitationController extends Controller
{
    public function index(TenantContext $context, MembershipLevelService $levels): View
    {
        $tenant = $context->requireTenant();
        $levels->ensureDefaultLevel($tenant);

        return view('tenant.manage.membership-invitations', [
            'tenant' => $tenant,
            'levels' => TenantMembershipLevel::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->get(),
            'invitations' => TenantMembershipInvitation::query()->where('tenant_id', $tenant->id)->with(['level', 'inviter', 'acceptedBy'])->latest('id')->paginate(50),
        ]);
    }

    public function store(Request $request, TenantContext $context, TenantMembershipAccessService $access): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isActive(), 404);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'membership_level_id' => ['required', 'integer', 'exists:tenant_membership_levels,id'],
            'expires_days' => ['required', 'integer', 'min:1', 'max:30'],
            'message' => ['nullable', 'string', 'max:1500'],
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Membership invitations currently require an existing Private Gather account for that email address.']);
        }
        abort_unless($user->status === 'active', 422, 'That Private Gather account is not active.');
        $access->assertEligible($tenant, $user);
        if (TenantMembership::hasActiveMembership($user, $tenant->id)) {
            throw ValidationException::withMessages(['email' => 'That member already has active access to this organization.']);
        }

        $level = TenantMembershipLevel::query()->findOrFail($data['membership_level_id']);
        abort_unless((int) $level->tenant_id === (int) $tenant->id && $level->is_active, 404);
        $domain = $tenant->domains()->where('is_primary', true)->where('status', 'active')->whereNotNull('verified_at')->first();
        if (! $domain) {
            throw ValidationException::withMessages(['membership' => 'Activate a verified primary site address before sending membership invitations.']);
        }

        $rawToken = Str::random(64);
        $digest = hash('sha256', $rawToken);
        $invitation = DB::transaction(function () use ($request, $tenant, $level, $email, $data, $digest): TenantMembershipInvitation {
            TenantMembershipInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('status', TenantMembershipInvitation::STATUS_PENDING)
                ->update(['status' => TenantMembershipInvitation::STATUS_REVOKED, 'revoked_at' => now(), 'updated_at' => now()]);

            return TenantMembershipInvitation::create([
                'tenant_id' => $tenant->id,
                'membership_level_id' => $level->id,
                'invited_by' => $request->user()->id,
                'email' => $email,
                'token_digest' => $digest,
                'status' => TenantMembershipInvitation::STATUS_PENDING,
                'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
                'expires_at' => now()->addDays((int) $data['expires_days']),
            ]);
        });

        $acceptUrl = TenantUrl::to($domain, '/membership-invite/'.$rawToken);
        $mailError = null;
        try {
            Mail::to($email)->send(new MembershipInvitationMail($tenant, $level, $acceptUrl, $invitation->message));
        } catch (\Throwable $exception) {
            report($exception);
            $mailError = 'The invitation was created, but email delivery failed. Copy the private link below and send it through a trusted channel.';
        }

        Audit::write('tenant.membership.invitation.created', $invitation, after: [
            'email' => $email,
            'membership_level_id' => $level->id,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'delivery' => $mailError ? 'manual_required' : 'email_sent',
        ], tenantId: $tenant->id, request: $request);

        return back()->with('status', $mailError ?: 'Membership invitation sent to '.$email.'.')->with('membership_invite_link', $acceptUrl);
    }

    public function revoke(Request $request, TenantContext $context, TenantMembershipInvitation $invitation): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $invitation->tenant_id === (int) $tenant->id, 404);
        abort_unless($invitation->status === TenantMembershipInvitation::STATUS_PENDING, 422, 'Only pending invitations can be revoked.');
        $before = $invitation->toArray();
        $invitation->update(['status' => TenantMembershipInvitation::STATUS_REVOKED, 'revoked_at' => now()]);
        Audit::write('tenant.membership.invitation.revoked', $invitation, before: $before, after: $invitation->fresh()->toArray(), tenantId: $tenant->id, request: $request);
        return back()->with('status', 'Membership invitation revoked.');
    }

    public function showAccept(Request $request, TenantContext $context, string $token): View
    {
        $tenant = $context->requireTenant();
        $invitation = $this->resolve($token, $tenant->id);
        $this->assertRecipient($request->user(), $invitation);
        return view('tenant.membership.accept-invitation', [
            'tenant' => $tenant,
            'invitation' => $invitation->load(['level', 'inviter']),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, TenantContext $context, string $token, TenantMembershipAccessService $access, MembershipLevelService $levels): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $user = $request->user();
        $request->validate([
            'rules_ack' => ['accepted'],
            'privacy_ack' => ['accepted'],
        ], [
            'rules_ack.accepted' => 'You must acknowledge the organization rules and consent standards.',
            'privacy_ack.accepted' => 'You must acknowledge the organization privacy and discretion policy.',
        ]);

        DB::transaction(function () use ($request, $tenant, $token, $access, $levels, $user): void {
            $invitation = TenantMembershipInvitation::query()->where('token_digest', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            abort_unless((int) $invitation->tenant_id === (int) $tenant->id, 404);
            $this->expireIfDue($invitation);
            abort_unless($invitation->isAcceptable(), 410, 'This membership invitation is no longer active.');
            $this->assertRecipient($user, $invitation);
            $access->assertEligible($tenant, $user);

            $level = $invitation->level;
            if (! $level || ! $level->is_active) $level = $levels->ensureDefaultLevel($tenant);
            $levels->applyLevel($tenant, $user, $level, $invitation->inviter, 'invitation');
            $invitation->update(['status' => TenantMembershipInvitation::STATUS_ACCEPTED, 'accepted_by' => $user->id, 'accepted_at' => now()]);
            Audit::write('tenant.membership.invitation.accepted', $invitation, after: [
                'accepted_by' => $user->id,
                'membership_level_id' => $level->id,
                'rules_acknowledged' => true,
                'privacy_acknowledged' => true,
            ], tenantId: $tenant->id, request: $request);
        });

        return redirect()->route('member.membership.show')->with('status', 'Your membership with '.$tenant->name.' is now active.');
    }

    private function resolve(string $token, int $tenantId): TenantMembershipInvitation
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);
        $invitation = TenantMembershipInvitation::query()->where('token_digest', hash('sha256', $token))->firstOrFail();
        abort_unless((int) $invitation->tenant_id === $tenantId, 404);
        $this->expireIfDue($invitation);
        abort_unless($invitation->isAcceptable(), 410, 'This membership invitation is no longer active.');
        return $invitation;
    }

    private function expireIfDue(TenantMembershipInvitation $invitation): void
    {
        if ($invitation->status === TenantMembershipInvitation::STATUS_PENDING && $invitation->expires_at->lte(now())) {
            $invitation->update(['status' => TenantMembershipInvitation::STATUS_EXPIRED]);
        }
    }

    private function assertRecipient(User $user, TenantMembershipInvitation $invitation): void
    {
        abort_unless(hash_equals(Str::lower(trim($invitation->email)), Str::lower(trim($user->email))), 403, 'This invitation belongs to a different Private Gather account.');
    }
}
