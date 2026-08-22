<?php

namespace App\Http\Controllers;

use App\Models\TenantMembershipApplication;
use App\Support\Audit;
use App\Support\TenantMembership;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembershipApplicationController extends Controller
{
    public function create(Request $request, TenantContext $context): View
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
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isActive(), 404);

        $user = $request->user();
        abort_unless($user && $user->isAdult() && $user->adult_confirmed_at, 403);

        if (TenantMembership::hasActiveMembership($user, $tenant->id)) {
            return back()->with('status', 'You already have active membership with '.$tenant->name.'.');
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

        $data = $request->validate([
            'profile_type' => ['required', 'in:individual,couple'],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'introduction' => ['required', 'string', 'min:20', 'max:2500'],
            'rules_ack' => ['accepted'],
            'privacy_ack' => ['accepted'],
        ], [
            'rules_ack.accepted' => 'You must acknowledge the club or organization rules and consent standards.',
            'privacy_ack.accepted' => 'You must acknowledge the privacy and discretion policy.',
        ]);

        $application = TenantMembershipApplication::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'profile_type' => $data['profile_type'],
            'status' => 'pending',
            'referred_by' => filled($data['referred_by'] ?? null) ? trim($data['referred_by']) : null,
            'introduction' => trim($data['introduction']),
            'answers' => [
                'rules_acknowledged' => true,
                'privacy_acknowledged' => true,
                'adult_confirmed' => true,
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
