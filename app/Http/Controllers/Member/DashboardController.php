<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\EventRsvp;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context)
    {
        $tenantId = $context->check() ? $context->id() : null;
        $rsvps = EventRsvp::query()
            ->with('event.tenant')
            ->where('user_id', $request->user()->id);

        if ($tenantId !== null) {
            $rsvps->whereHas('event', fn ($event) => $event->where('tenant_id', $tenantId));
        }

        $user = $request->user()->load('profile');
        if ($tenantId !== null) {
            $user->load(['tenants' => fn ($tenants) => $tenants->where('tenants.id', $tenantId)]);
        } else {
            $user->load('tenants');
        }

        return view('member.dashboard', [
            'user' => $user,
            'rsvps' => $rsvps->latest()->limit(12)->get(),
        ]);
    }
}
