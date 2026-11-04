<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Services\TenantAnnouncementService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

final class AnnouncementController extends Controller
{
    public function index(TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        return view('tenant.manage.announcements', [
            'tenant' => $tenant,
            'announcements' => DB::table('tenant_announcements')->where('tenant_id', $tenant->id)->orderByDesc('id')->paginate(30),
            'events' => $tenant->events()->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->limit(100)->get(['id', 'title', 'starts_at']),
            'membershipLevels' => $tenant->membershipLevels()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'chapters' => CommunityGroup::query()->where('tenant_id', $tenant->id)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('status', 'active')->orderBy('region')->orderBy('city')->orderBy('name')->get(['id', 'name', 'city', 'region']),
        ]);
    }

    public function store(Request $request, TenantContext $context, TenantAnnouncementService $publisher): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', 'in:all_members,staff,event_attendees,membership_level,chapter_members'],
            'event_id' => ['nullable', 'integer'],
            'membership_level_id' => ['nullable', 'integer'],
            'community_group_id' => ['nullable', 'integer'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:action,schedule'],
            'action' => ['required', 'in:draft,publish,schedule'],
        ]);

        $eventId = $this->validatedEventId($tenant->id, $data['audience'], $data['event_id'] ?? null);
        $membershipLevelId = $this->validatedMembershipLevelId($tenant->id, $data['audience'], $data['membership_level_id'] ?? null);
        $chapterId = $this->validatedChapterId($tenant->id, $data['audience'], $data['community_group_id'] ?? null);
        // Normalize HTML datetime-local input to Carbon before the query builder
        // persists it. This keeps SQLite/MySQL comparisons consistent because
        // both planned publication and due-query timestamps use SQL datetime form.
        $scheduledAt = $data['action'] === 'schedule' ? Carbon::parse((string) $data['scheduled_at']) : null;
        $expiresAt = filled($data['expires_at'] ?? null) ? Carbon::parse((string) $data['expires_at']) : null;

        $announcementId = DB::transaction(function () use ($request, $tenant, $data, $eventId, $membershipLevelId, $chapterId, $scheduledAt, $expiresAt): int {
            $id = (int) DB::table('tenant_announcements')->insertGetId([
                'tenant_id' => $tenant->id,
                'created_by' => $request->user()->id,
                'event_id' => $eventId,
                'membership_level_id' => $membershipLevelId,
                'community_group_id' => $chapterId,
                'title' => trim($data['title']),
                'body' => trim($data['body']),
                'audience' => $data['audience'],
                'priority' => $data['priority'],
                'status' => $data['action'] === 'schedule' ? 'scheduled' : 'draft',
                'recipient_count' => 0,
                'published_at' => $scheduledAt,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit($request, $tenant->id, 'announcement.created', $id, [
                'audience' => $data['audience'],
                'priority' => $data['priority'],
                'event_id' => $eventId,
                'membership_level_id' => $membershipLevelId,
                'community_group_id' => $chapterId,
                'scheduled_at' => $scheduledAt?->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
            ]);

            return $id;
        });

        if ($data['action'] === 'publish') {
            $publisher->publish(
                $tenant->id,
                $announcementId,
                $request->user()->id,
                $request->ip(),
                $request->userAgent(),
                false,
                'operator',
            );
        }

        $message = match ($data['action']) {
            'publish' => 'Announcement published.',
            'schedule' => 'Announcement scheduled.',
            default => 'Announcement saved as a draft.',
        };

        return redirect()->route('tenant.announcements.index')->with('status', $message);
    }

    public function publish(Request $request, TenantContext $context, TenantAnnouncementService $publisher, int $announcement): RedirectResponse
    {
        $tenant = $context->requireTenant();
        try {
            $publisher->publish(
                $tenant->id,
                $announcement,
                $request->user()->id,
                $request->ip(),
                $request->userAgent(),
                false,
                'operator',
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('status', 'Announcement published.');
    }

    private function validatedEventId(int $tenantId, string $audience, mixed $eventId): ?int
    {
        if ($audience !== 'event_attendees') return null;
        abort_if(! is_numeric($eventId), 422, 'Choose an event for the attendee audience.');
        $id = (int) $eventId;
        abort_unless(DB::table('events')->where('id', $id)->where('tenant_id', $tenantId)->exists(), 422, 'Choose an event owned by this organization.');
        return $id;
    }

    private function validatedMembershipLevelId(int $tenantId, string $audience, mixed $levelId): ?int
    {
        if ($audience !== 'membership_level') return null;
        abort_if(! is_numeric($levelId), 422, 'Choose a membership level for this audience.');
        $id = (int) $levelId;
        abort_unless(DB::table('tenant_membership_levels')->where('id', $id)->where('tenant_id', $tenantId)->where('is_active', true)->exists(), 422, 'Choose an active membership level owned by this organization.');
        return $id;
    }

    private function validatedChapterId(int $tenantId, string $audience, mixed $chapterId): ?int
    {
        if ($audience !== 'chapter_members') return null;
        abort_if(! is_numeric($chapterId), 422, 'Choose a chapter for this audience.');
        $id = (int) $chapterId;
        abort_unless(
            DB::table('community_groups')->where('id', $id)->where('tenant_id', $tenantId)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('status', 'active')->exists(),
            422,
            'Choose an active chapter owned by this organization.',
        );
        return $id;
    }

    private function audit(Request $request, int $tenantId, string $action, int $subjectId, array $after): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => 'tenant_announcement',
            'subject_id' => $subjectId,
            'before' => null,
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }
}
