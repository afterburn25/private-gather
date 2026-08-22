<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ManageController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $tenant = $context->requireTenant()
            ->loadCount([
                'events',
                'membershipApplications as pending_membership_applications_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->load(['domains', 'subscription.plan', 'branding']);

        return view('tenant.manage.dashboard', ['tenant' => $tenant]);
    }
}
