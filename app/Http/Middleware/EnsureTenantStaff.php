<?php
namespace App\Http\Middleware;

use App\Models\Event;
use App\Services\EventStaffAccessService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantStaff
{
    public function __construct(private TenantContext $context, private EventStaffAccessService $eventAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->requireTenant();
        $user = $request->user();
        abort_unless($user, 401);
        if ($user->is_platform_admin) return $next($request);

        $membership = $user->tenants()->whereKey($tenant->id)->first()?->pivot;
        if ($membership && $membership->status === 'active' && in_array($membership->role, ['owner', 'admin', 'manager', 'staff', 'checkin'], true)) {
            return $next($request);
        }

        // A normal active member can be delegated check-in access to one event
        // without being promoted to organization-wide staff.
        if ($membership && $membership->status === 'active' && $request->route('event')) {
            $routeEvent = $request->route('event');
            $event = $routeEvent instanceof Event
                ? $routeEvent
                : Event::where('tenant_id', $tenant->id)->whereKey((int) $routeEvent)->first();
            if ($event && $this->eventAccess->can($user, $event, EventStaffAccessService::CAP_CHECKIN)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
