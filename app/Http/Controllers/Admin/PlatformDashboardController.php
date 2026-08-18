<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Edition;

class PlatformDashboardController extends Controller
{
    public function __invoke()
    {
        $stats = Edition::isHosted()
            ? [
                'users' => User::count(),
                'tenants' => Tenant::count(),
                'events' => Event::count(),
                'orders' => Order::count(),
                'open_reports' => Report::where('status', 'open')->count(),
            ]
            : [
                'members' => User::count(),
                'pending_members' => User::where('status', 'pending')->count(),
                'events' => Event::count(),
                'orders' => Order::count(),
                'open_reports' => Report::where('status', 'open')->count(),
            ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentTenants' => Edition::isHosted() ? Tenant::latest()->limit(8)->get() : collect(),
            'localTenant' => Edition::isSelfHosted()
                ? Tenant::query()->when(Edition::selfHostedTenantId(), fn ($q, $id) => $q->whereKey($id))->where('status', 'active')->first()
                : null,
        ]);
    }
}
