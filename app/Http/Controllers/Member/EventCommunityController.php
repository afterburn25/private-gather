<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EventCommunityController extends Controller
{
    public function show(Request $request, Event $event, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $this->assertEventAccess($request, $event, (int) $tenant->id);

        $posts = CommunityPost::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->with([
                'user:id,name,display_name',
                'comments' => fn ($query) => $query->where('status', 'active')->oldest()->with('user:id,name,display_name'),
                'reactions',
            ])
            ->latest()
            ->paginate(25);

        return view('member.community.context', [
            'tenant' => $tenant,
            'posts' => $posts,
            'contextKind' => 'EVENT COMMUNITY',
            'contextTitle' => $event->title,
            'contextSubtitle' => 'A private conversation space for members attending this event.',
            'postAction' => route('events.community.store', $event),
            'backUrl' => route('events.show', $event),
            'canModerate' => $this->tenantManager($request, (int) $tenant->id),
        ]);
    }

    public function store(Request $request, Event $event, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertEventAccess($request, $event, (int) $tenant->id);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        CommunityPost::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
            'post_type' => 'event',
            'visibility' => 'members',
            'share_to_club_wall' => false,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to the event community.');
    }

    private function assertEventAccess(Request $request, Event $event, int $tenantId): void
    {
        abort_unless((int) $event->tenant_id === $tenantId && $event->status === 'published', 404);

        if (! in_array((string) $event->visibility, ['private', 'invite_only'], true)) {
            return;
        }

        if ($this->tenantManager($request, $tenantId)) {
            return;
        }

        abort_unless(
            $event->rsvps()
                ->where('user_id', $request->user()->id)
                ->where('status', 'approved')
                ->exists(),
            403,
            'This event community is available only to approved attendees.'
        );
    }

    private function tenantManager(Request $request, int $tenantId): bool
    {
        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;

        return $membership && in_array((string) $membership->role, ['owner', 'admin', 'manager'], true);
    }
}
