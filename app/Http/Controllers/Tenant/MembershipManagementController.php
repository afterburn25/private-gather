<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MembershipManagementController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $query = DB::table('tenant_users')
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->leftJoin('membership_levels', 'membership_levels.id', '=', 'tenant_users.membership_level_id')
            ->where('tenant_users.tenant_id', $tenant->id)
            ->where('tenant_users.role', 'member')
            ->select([
                'users.id', 'users.name', 'users.display_name', 'users.email', 'users.status as user_status',
                'tenant_users.status as membership_status', 'tenant_users.membership_level_id',
                'tenant_users.membership_started_at', 'tenant_users.membership_expires_at', 'tenant_users.membership_billing_status',
                'membership_levels.name as membership_level_name',
            ])
            ->orderByRaw("CASE tenant_users.status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('users.display_name')->orderBy('users.name');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('users.email', 'like', '%'.$search.'%')
                    ->orWhere('users.display_name', 'like', '%'.$search.'%')
                    ->orWhere('users.name', 'like', '%'.$search.'%');
            });
        }

        return view('tenant.manage.growth-members', [
            'tenant' => $tenant,
            'members' => $query->paginate(100)->withQueryString(),
            'membershipLevels' => DB::table('membership_levels')
                ->where('tenant_id', $tenant->id)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $membership = DB::table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('role', 'member')
            ->first();
        abort_unless($membership, 404);

        $data = $request->validate([
            'status' => ['required', 'in:pending,active,suspended,banned'],
            'membership_level_id' => ['nullable', 'integer'],
            'membership_started_at' => ['nullable', 'date'],
            'membership_expires_at' => ['nullable', 'date'],
            'membership_billing_status' => ['nullable', 'in:active,pending,past_due,canceled,comped'],
        ]);

        $levelId = isset($data['membership_level_id']) && $data['membership_level_id'] !== ''
            ? (int) $data['membership_level_id']
            : null;
        if ($levelId && ! DB::table('membership_levels')->where('tenant_id', $tenant->id)->where('id', $levelId)->exists()) {
            abort(404);
        }
        if (! empty($data['membership_started_at']) && ! empty($data['membership_expires_at'])
            && strtotime((string) $data['membership_expires_at']) < strtotime((string) $data['membership_started_at'])) {
            throw ValidationException::withMessages(['membership_expires_at' => 'Membership expiration cannot be before the start date.']);
        }

        DB::table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update([
                'status' => $data['status'],
                'membership_level_id' => $levelId,
                'membership_started_at' => $data['membership_started_at'] ?? null,
                'membership_expires_at' => $data['membership_expires_at'] ?? null,
                'membership_billing_status' => $data['membership_billing_status'] ?? null,
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Member membership settings updated.');
    }
}
