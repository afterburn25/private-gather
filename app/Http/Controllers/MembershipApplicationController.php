<?php

namespace App\Http\Controllers;

use App\Models\TenantMembershipApplication;
use App\Services\MembershipLevelService;
use App\Services\TenantMembershipAccessService;
use App\Support\Audit;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembershipApplicationController extends Controller
{
    public function create(Request $request, TenantContext $context, TenantMembershipAccessService $access): View
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isActive(), 404);

        $user = $request->user();
        abort_unless($user && $user->isAdult() && $user->adult_confirmed_at, 403);

        return view('tenant.membership.apply', [
            'tenant' => $tenant,
            'activeMember' => TenantMembership::hasActiveMembership($user, $tenant->id),
            'latestApplication' => TenantMembershipApplication::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->latest('id')
                ->first(),
            'accessMode' => $access->mode($tenant),
            'minimumAge' => $access->minimumAge($tenant),
            'allowedProfileTypes' => $access->allowedProfileTypes($tenant),
        ]);
    }

    public function store(
        Request $request,
        TenantContext $context,
        TenantMembershipAccessService $access,
        MembershipLevelService $levels,
    ): RedirectResponse {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isActive(), 404);

        $user = $request->user();
        abort_unless($user && $user->isAdult() && $user->adult_confirmed_at, 403);

        $mode = $access->mode($tenant);
        abort_if($mode === 'invite_only', 403, 'Membership in this organization is invitation only.');

        if (TenantMembership::hasActiveMembership($user, $tenant->id)) {
            return back()->with('status', 'You already have active membership with '.$tenant->name.'.');
        }

        $rules = [
            'profile_type' => ['required', 'in:individual,couple'],
            'rules_ack' => ['accepted'],
            'privacy_ack' => ['accepted'],
        ];
        if ($mode === 'application') {
            $rules['referred_by'] = ['nullable', 'string', 'max:255'];
            $rules['introduction'] = ['required', 'string', 'min:20', 'max:2500'];
        }
        $data = $request->validate($rules, [
            'rules_ack.accepted' => 'You must acknowledge the club or organization rules and consent standards.',
            'privacy_ack.accepted' => 'You must acknowledge the privacy and discretion policy.',
        ]);

        if ($mode === 'open' && ! $user->profile()->exists()) {
            throw ValidationException::withMessages([
                'profile_type' => 'Complete your Private Gather profile before activating open membership.',
            ]);
        }

        $profileType = $access->assertEligible($tenant, $user, $data['profile_type']);

        if ($mode === 'open') {
            $level = $levels->ensureDefaultLevel($tenant);
            $levels->applyLevel($tenant, $user, $level, $user, 'self_open');
            return back()->with('status', 'Your membership with '.$tenant->name.' is now active.');
        }

        $existing = TenantMembershipApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'more_info', 'approved'])
            ->latest('id')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'membership' => 'You already have an active membership application for this club or organization.',
            ]);
        }

        $application = TenantMembershipApplication::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'profile_type' => $profileType,
            'status' => 'pending',
            'referred_by' => filled($data['referred_by'] ?? null) ? trim($data['referred_by']) : null,
            'introduction' => trim($data['introduction']),
            'answers' => [
                'rules_acknowledged' => true,
                'privacy_acknowledged' => true,
                'adult_confirmed' => true,
                'minimum_age' => $access->minimumAge($tenant),
            ],
        ]);

        Audit::write(
            'tenant.membership.application.submitted',
            $application,
            after: [
                'status' => 'pending',
                'profile_type' => $application->profile_type,
            ],
            tenantId: $tenant->id,
            request: $request,
        );

        return back()->with('status', 'Your membership application was submitted to '.$tenant->name.'.');
    }
}
