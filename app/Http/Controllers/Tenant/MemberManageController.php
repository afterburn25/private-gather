<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class MemberManageController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        abort_unless(Edition::isSelfHosted(), 404);

        $tenant = $context->requireTenant();
        $query = $tenant->users()->orderByRaw("CASE users.status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderBy('users.display_name')
            ->orderBy('users.name');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('users.email', 'like', '%'.$search.'%')
                    ->orWhere('users.display_name', 'like', '%'.$search.'%')
                    ->orWhere('users.name', 'like', '%'.$search.'%');
            });
        }

        return view('tenant.manage.members', [
            'tenant' => $tenant,
            'members' => $query->paginate(100)->withQueryString(),
        ]);
    }

    public function update(Request $request, User $user, TenantContext $context)
    {
        abort_unless(Edition::isSelfHosted(), 404);

        $tenant = $context->requireTenant();
        $membership = $tenant->users()->whereKey($user->id)->first()?->pivot;
        abort_unless($membership, 404);

        $data = $request->validate([
            'status' => 'required|in:pending,active,suspended,banned',
        ]);

        if ($user->is($request->user())) {
            abort_if($data['status'] !== 'active', 422, 'You cannot disable your own current account.');
        }

        $before = ['status' => $user->status];
        $user->update(['status' => $data['status']]);

        Audit::write(
            'self_hosted.member.status',
            $user,
            before: $before,
            after: ['status' => $user->status],
            tenantId: $tenant->id,
            request: $request,
        );

        return back()->with('status', 'Member account updated.');
    }
}
