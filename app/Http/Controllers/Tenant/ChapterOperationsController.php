<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\Event;
use App\Models\Tenant;
use App\Services\ChapterAccessService;
use App\Services\EventVenueAccessService;
use App\Services\TenantAnnouncementService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ChapterOperationsController extends Controller
{
    public function workspace(Request $request, TenantContext $context, int $chapter, ChapterAccessService $access): View
    {
        [$tenant, $group] = $this->authorizedChapter($context, $chapter, $request, $access);
        $memberIds = DB::table('community_group_members')->where('community_group_id', $group->id)->where('status', 'active')->pluck('user_id');

        return view('tenant.manage.chapter-workspace', [
            'tenant' => $tenant,
            'chapter' => $group->load('chapterLead'),
            'events' => Event::query()->where('tenant_id', $tenant->id)->where('community_group_id', $group->id)->whereNull('parent_event_id')->latest('starts_at')->limit(20)->get(),
            'announcements' => DB::table('tenant_announcements')->where('tenant_id', $tenant->id)->where('community_group_id', $group->id)->latest()->limit(20)->get(),
            'activeMembers' => $memberIds->count(),
            'eventAttendance' => DB::table('event_checkins')->join('events', 'events.id', '=', 'event_checkins.event_id')->where('events.tenant_id', $tenant->id)->where('events.community_group_id', $group->id)->count(),
            'postsCount' => DB::table('community_posts')->where('tenant_id', $tenant->id)->where('community_group_id', $group->id)->where('status', 'active')->count(),
        ]);
    }

    public function updateBranding(Request $request, TenantContext $context, int $chapter, ChapterAccessService $access): RedirectResponse
    {
        [$tenant, $group] = $this->authorizedChapter($context, $chapter, $request, $access);
        $data = $request->validate([
            'tagline' => ['nullable', 'string', 'max:240'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $before = $group->branding;
        $group->update(['branding' => [
            'tagline' => trim((string) ($data['tagline'] ?? '')) ?: null,
            'primary_color' => $data['primary_color'] ?? null,
            'accent_color' => $data['accent_color'] ?? null,
        ]]);
        Audit::write('chapter.branding.updated', $group, before: $before, after: $group->fresh()->branding, tenantId: $tenant->id, request: $request);
        return back()->with('status', 'Chapter presentation updated.');
    }

    public function storeEvent(Request $request, TenantContext $context, int $chapter, ChapterAccessService $access, EventVenueAccessService $venueAccess): RedirectResponse
    {
        [$tenant, $group] = $this->authorizedChapter($context, $chapter, $request, $access);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'public_location_label' => ['nullable', 'string', 'max:190'],
            'exact_address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'],
        ]);

        $accessDefault = (string) (($tenant->settings ?? [])['event_access_default'] ?? 'members');
        [$visibility, $rsvpMode] = match ($accessDefault) {
            'public' => ['public', 'instant'],
            'approval' => ['public', 'approval'],
            'invite_only' => ['invite_only', 'invite_only'],
            default => ['members', 'instant'],
        };

        $event = Event::create([
            'tenant_id' => $tenant->id,
            'community_group_id' => $group->id,
            'title' => trim($data['title']),
            'slug' => $this->uniqueEventSlug($tenant->id, $data['title']),
            'summary' => trim((string) ($data['summary'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'visibility' => $visibility,
            'rsvp_mode' => $rsvpMode,
            'status' => $data['status'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => (string) (($tenant->settings ?? [])['timezone'] ?? 'America/Chicago'),
            'capacity' => $data['capacity'] ?? null,
            'city' => trim((string) ($data['city'] ?? $group->city ?? '')) ?: null,
            'region' => trim((string) ($data['region'] ?? $group->region ?? '')) ?: null,
            'public_location_label' => trim((string) ($data['public_location_label'] ?? '')) ?: null,
            'exact_address' => trim((string) ($data['exact_address'] ?? '')) ?: null,
            'exact_address_visibility' => $venueAccess->defaultVisibility($tenant->settings ?? []),
            'waitlist_enabled' => true,
            'requires_verified_profile' => false,
        ]);
        Audit::write('chapter.event.created', $event, after: ['chapter_id' => $group->id, 'status' => $event->status, 'starts_at' => $event->starts_at?->toIso8601String()], tenantId: $tenant->id, request: $request);
        return back()->with('status', 'Chapter event created. Full ticketing and advanced event controls remain available in Event Studio.');
    }

    public function storeAnnouncement(Request $request, TenantContext $context, int $chapter, ChapterAccessService $access, TenantAnnouncementService $publisher): RedirectResponse
    {
        [$tenant, $group] = $this->authorizedChapter($context, $chapter, $request, $access);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:30000'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'delivery' => ['required', 'in:draft,now,scheduled'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($data['delivery'] === 'scheduled' && empty($data['scheduled_at'])) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a future publication time for a scheduled chapter announcement.']);
        }

        $id = DB::table('tenant_announcements')->insertGetId([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
            'event_id' => null,
            'membership_level_id' => null,
            'community_group_id' => $group->id,
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'audience' => 'chapter_members',
            'priority' => $data['priority'],
            'status' => $data['delivery'] === 'scheduled' ? 'scheduled' : 'draft',
            'recipient_count' => 0,
            'published_at' => $data['delivery'] === 'scheduled' ? $data['scheduled_at'] : null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($data['delivery'] === 'now') {
            $publisher->publish($tenant->id, $id, $request->user()->id, $request->ip(), $request->userAgent(), false, 'chapter_operator');
            return back()->with('status', 'Chapter announcement published to active chapter members.');
        }
        Audit::write('chapter.announcement.created', $group, after: ['announcement_id' => $id, 'delivery' => $data['delivery']], tenantId: $tenant->id, request: $request);
        return back()->with('status', $data['delivery'] === 'scheduled' ? 'Chapter announcement scheduled.' : 'Chapter announcement saved as draft.');
    }

    public function show(Request $request, TenantContext $context, string $slug, ChapterAccessService $access): View
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isOrganization(), 404);
        $group = CommunityGroup::query()->where('tenant_id', $tenant->id)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('slug', $slug)->where('status', 'active')->firstOrFail();
        abort_unless($access->canView($tenant, $group, $request->user()), 404);

        $isChapterMember = $request->user() ? $access->isActiveChapterMember($group->id, $request->user()->id) : false;
        $announcements = collect();
        if ($isChapterMember) {
            $announcements = DB::table('tenant_announcements')
                ->where('tenant_id', $tenant->id)
                ->where('community_group_id', $group->id)
                ->where('audience', 'chapter_members')
                ->where('status', 'published')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('published_at')
                ->limit(10)
                ->get();
        }

        return view('tenant.chapter', [
            'tenant' => $tenant,
            'chapter' => $group->load('chapterLead'),
            'events' => Event::query()->where('tenant_id', $tenant->id)->where('community_group_id', $group->id)->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(20)->get(),
            'announcements' => $announcements,
            'isChapterMember' => $isChapterMember,
        ]);
    }

    private function authorizedChapter(TenantContext $context, int $chapterId, Request $request, ChapterAccessService $access): array
    {
        $tenant = $context->requireTenant();
        abort_unless($tenant->isOrganization(), 404);
        $chapter = CommunityGroup::query()->where('tenant_id', $tenant->id)->where('group_type', CommunityGroup::TYPE_CHAPTER)->where('status', 'active')->findOrFail($chapterId);
        abort_unless($access->canOperate($tenant, $chapter, $request->user()), 403);
        return [$tenant, $chapter];
    }

    private function uniqueEventSlug(int $tenantId, string $title): string
    {
        $base = Str::slug($title) ?: 'chapter-event';
        $slug = $base;
        $suffix = 2;
        while (Event::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;
        return $slug;
    }
}
