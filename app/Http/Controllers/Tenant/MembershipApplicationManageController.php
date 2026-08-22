<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantMembershipApplication;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipApplicationManageController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $tenant = $context->requireTenant();

        $query = TenantMembershipApplication::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user.profile', 'reviewer'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'more_info' THEN 1 ELSE 2 END")
            ->latest('id');

        if ($status = trim((string) $request->query('status'))) {
            if (in_array($status, ['pending', 'more_info', 'approved', 'declined'], true)) {
                $query->where('status', $status);
            }
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->whereHas('user', function ($builder) use ($search): void {
                $builder->where('email', 'like', '%'.$search.'%')
                    ->orWhere('display_name', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        return view('tenant.manage.membership-applications', [
            'tenant' => $tenant,
            'applications' => $query->paginate(50)->withQueryString(),
        ]);
    }

    public function update(
        Request $request,
        TenantMembershipApplication $application,
        TenantContext $context,
    ): RedirectResponse {
        $tenant = $context->requireTenant();
        abort_unless($application->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,declined,more_info'],
            'decision_note' => ['nullable', 'string', 'max:2500'],
        ]);

        $before = [
            'status' => $application->status,
            'reviewed_by' => $application->reviewed_by,
        ];

        DB::transaction(function () use ($application, $tenant, $request, $data): void {
            $application->update([
                'status' => $data['decision'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'decision_note' => filled($data['decision_note'] ?? null)
                    ? trim($data['decision_note'])
                    : null,
            ]);

            if ($data['decision'] !== 'approved') {
                return;
            }

            $existing = $tenant->users()->whereKey($application->user_id)->first();

            if (! $existing) {
                $tenant->users()->attach($application->user_id, [
                    'role' => 'member',
                    'status' => 'active',
                ]);

                return;
            }

            if ($existing->pivot->role === 'member') {
                $tenant->users()->updateExistingPivot($application->user_id, [
                    'status' => 'active',
                ]);
            }
        });

        Audit::write(
            'tenant.membership.application.reviewed',
            $application,
            before: $before,
            after: [
                'status' => $application->fresh()->status,
                'reviewed_by' => $request->user()->id,
            ],
            tenantId: $tenant->id,
            request: $request,
        );

        return back()->with('status', match ($data['decision']) {
            'approved' => 'Membership approved. The member now has access to '.$tenant->name.'.',
            'declined' => 'Membership application declined.',
            default => 'The application was marked as needing more information.',
        });
    }
}
