<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;
use App\Tenancy\TenantContext;

class PlatformDashboardController extends Controller
{
    public function __invoke(TenantContext $context)
    {
        if (Edition::isHosted()) {
            return view('admin.dashboard', [
                'stats' => [
                    'users' => User::count(),
                    'tenants' => Tenant::count(),
                    'events' => Event::count(),
                    'orders' => Order::count(),
                    'open_reports' => Report::where('status', 'open')->count(),
                ],
                'recentTenants' => Tenant::latest()->limit(8)->get(),
                'localTenant' => null,
            ]);
        }

        $tenant = $context->requireTenant();
        $members = User::query()->whereHas(
            'tenants',
            fn ($tenantQuery) => $tenantQuery->whereKey($tenant->id)
        );

        return view('admin.dashboard', [
            'stats' => [
                'members' => (clone $members)->count(),
                'pending_members' => (clone $members)->where('users.status', 'pending')->count(),
                'events' => Event::where('tenant_id', $tenant->id)->count(),
                'orders' => Order::where('tenant_id', $tenant->id)->count(),
                'open_reports' => Report::where('tenant_id', $tenant->id)->where('status', 'open')->count(),
            ],
            'recentTenants' => collect(),
            'localTenant' => $tenant,
        ]);
    }
}
