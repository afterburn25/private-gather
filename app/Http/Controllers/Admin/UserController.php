<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $query = User::query();

        if (Edition::isSelfHosted()) {
            $tenant = $context->requireTenant();
            $query->whereHas('tenants', fn ($tenantQuery) => $tenantQuery->whereKey($tenant->id));
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('email', 'like', '%'.$search.'%')
                ->orWhere('display_name', 'like', '%'.$search.'%')
                ->orWhere('name', 'like', '%'.$search.'%'));
        }

        return view('admin.users.index', [
            'users' => $query->latest('users.created_at')->paginate(100)->withQueryString(),
        ]);
    }

    public function update(Request $request, User $user, TenantContext $context)
    {
        if (Edition::isSelfHosted()) {
            $tenant = $context->requireTenant();
            abort_unless($user->tenants()->whereKey($tenant->id)->exists(), 404);
        }

        $data = $request->validate([
            'status' => 'required|in:pending,active,suspended,banned',
            'is_platform_admin' => 'nullable|boolean',
        ]);
        $platformAdmin = $request->boolean('is_platform_admin');

        if ($user->id === $request->user()->id) {
            abort_if($data['status'] !== 'active', 422, 'You cannot disable your own current administrator account.');
            abort_unless($platformAdmin, 422, 'You cannot remove your own current administrator access.');
        }

        $user->update([
            'status' => $data['status'],
            'is_platform_admin' => $platformAdmin,
        ]);

        return back()->with('status', 'User updated.');
    }
}
