<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MembershipApplication;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClubMembershipController extends Controller
{
    public function apply(Request $request, Tenant $tenant, TenantContext $context): RedirectResponse
    {
        abort_if($context->check(), 404);
        abort_unless($tenant->type === Tenant::TYPE_CLUB && $tenant->status === 'active', 404);

        $user = $request->user();
        $existing = $user->tenants()->whereKey($tenant->id)->first()?->pivot;
        if ($existing && in_array($existing->status, ['active','pending'], true)) {
            return back()->with('status', $existing->status === 'active'
                ? 'You are already an active member of this club.'
                : 'Your application to this club is already pending.');
        }

        $mode = (string) data_get($tenant->settings, 'membership_registration', 'approval');
        $status = $mode === 'open' ? 'active' : 'pending';
        $profile = $user->profile;

        DB::transaction(function () use ($tenant, $user, $profile, $status): void {
            $tenant->users()->syncWithoutDetaching([
                $user->id => ['role' => 'member', 'status' => $status],
            ]);
            $tenant->users()->updateExistingPivot($user->id, ['role'=>'member','status'=>$status]);

            MembershipApplication::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'status' => $status === 'active' ? 'approved' : 'pending',
                    'answers' => [
                        'lifestyle_identity' => $profile?->lifestyle_identity,
                        'relationship_status' => $profile?->relationship_status,
                        'experience_level' => $profile?->experience_level,
                        'looking_for' => $profile?->looking_for ?? [],
                        'lifestyle_interests' => $profile?->lifestyle_interests ?? [],
                    ],
                    'reviewed_at' => $status === 'active' ? now() : null,
                ]
            );
        });

        return back()->with('status', $status === 'active'
            ? 'You joined '.$tenant->name.'.'
            : 'Your membership application was submitted to '.$tenant->name.'.');
    }
}
