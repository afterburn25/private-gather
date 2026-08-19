<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Analytics;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventPublicController extends Controller
{
    public function show(Request $request, TenantContext $context, Event $event, Analytics $analytics)
    {
        abort_unless($event->status === 'published', 404);
        $event->load([
            'tenant.branding',
            'ticketTypes' => fn ($query) => $query->where('active', true)->orderBy('price_cents'),
        ]);

        if ($context->check()) {
            abort_unless((int) $event->tenant_id === (int) $context->id(), 404);

            if ($event->visibility === 'members') {
                abort_unless($this->canSeeMembersEvent($request, (int) $event->tenant_id), 403);
            }

            if (in_array($event->visibility, ['private', 'invite_only'], true)) {
                abort_unless(
                    $request->user()
                    && $event->rsvps()
                        ->where('user_id', $request->user()->id)
                        ->where('status', 'approved')
                        ->exists(),
                    403
                );
            }
        } else {
            abort_unless($event->visibility === 'public', 404);
        }

        $analytics->record('event.view', $request, [], $event->tenant_id, $event->id);
        $questions = DB::table('event_questions')
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->get();

        return view('events.show', [
            'event' => $event,
            'remaining' => $event->remainingCapacity(),
            'questions' => $questions,
        ]);
    }

    private function canSeeMembersEvent(Request $request, int $tenantId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        return $user->tenants()
            ->whereKey($tenantId)
            ->wherePivot('status', 'active')
            ->exists();
    }
}
