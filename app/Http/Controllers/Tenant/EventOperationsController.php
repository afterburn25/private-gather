<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\CommunicationsService;
use App\Services\EventGuestPolicyService;
use App\Services\EventStaffAccessService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EventOperationsController extends Controller
{
    public function updateGuestPolicy(Request $request, TenantContext $context, Event $event)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $data = $request->validate([
            'guest_policy' => 'required|in:no_guests,allowed,approval_required',
            'max_party_size' => 'required|integer|min:1|max:10',
        ]);
        if ($data['guest_policy'] === EventGuestPolicyService::NONE) $data['max_party_size'] = 1;
        $before = $event->only(['guest_policy', 'max_party_size']);
        $event->update($data);
        Audit::write('event.guest_policy.updated', $event, $before, $event->fresh()->only(['guest_policy', 'max_party_size']), $context->id(), $request);
        return back()->with('status', 'Event guest policy updated. Existing RSVPs were not silently rewritten.');
    }

    public function assignStaff(Request $request, TenantContext $context, Event $event)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $data = $request->validate([
            'user_id' => 'required|integer',
            'capabilities' => 'required|array|min:1',
            'capabilities.*' => 'required|in:checkin,attendees,communications',
        ]);
        $activeMember = DB::table('tenant_users')
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->where('tenant_users.tenant_id', $context->id())
            ->where('tenant_users.user_id', $data['user_id'])
            ->where('tenant_users.status', 'active')
            ->where('users.status', 'active')
            ->exists();
        abort_unless($activeMember, 422, 'Event staff must be an active member or staff account in this organization.');

        $capabilities = collect($data['capabilities'])->unique()->sort()->values()->all();
        DB::table('event_staff_assignments')->updateOrInsert(
            ['event_id' => $event->id, 'user_id' => $data['user_id']],
            [
                'tenant_id' => $context->id(),
                'assigned_by' => $request->user()->id,
                'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        Audit::write('event.staff.assigned', $event, after: ['user_id' => (int) $data['user_id'], 'capabilities' => $capabilities], tenantId: $context->id(), request: $request);
        return back()->with('status', 'Event-scoped staff access saved. This does not grant organization-wide manager access.');
    }

    public function deactivateStaff(Request $request, TenantContext $context, Event $event, int $assignment)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $row = DB::table('event_staff_assignments')
            ->where('tenant_id', $context->id())
            ->where('event_id', $event->id)
            ->where('id', $assignment)
            ->first();
        abort_unless($row, 404);
        DB::table('event_staff_assignments')->where('id', $row->id)->update(['status' => 'inactive', 'updated_at' => now()]);
        Audit::write('event.staff.deactivated', $event, before: ['user_id' => (int) $row->user_id, 'capabilities' => json_decode((string) $row->capabilities, true)], tenantId: $context->id(), request: $request);
        return back()->with('status', 'Event-scoped access removed.');
    }

    public function workspace(Request $request, TenantContext $context, Event $event, EventStaffAccessService $access, EventGuestPolicyService $guestPolicy)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $caps = collect([
            EventStaffAccessService::CAP_CHECKIN,
            EventStaffAccessService::CAP_ATTENDEES,
            EventStaffAccessService::CAP_COMMUNICATIONS,
        ])->filter(fn (string $cap) => $access->can($request->user(), $event, $cap))->values();
        abort_if($caps->isEmpty(), 403);

        $membership = $request->user()->tenants()->whereKey($event->tenant_id)->first()?->pivot;
        $canManageEvent = (bool) $request->user()->is_platform_admin
            || ($membership && $membership->status === 'active' && in_array($membership->role, ['owner', 'admin', 'manager'], true));

        $rsvps = $caps->contains(EventStaffAccessService::CAP_ATTENDEES)
            ? $event->rsvps()->with('user')->whereIn('status', ['pending', 'approved'])->latest()->limit(200)->get()
            : collect();
        $staffAssignments = $canManageEvent
            ? DB::table('event_staff_assignments as assignments')
                ->join('users', 'users.id', '=', 'assignments.user_id')
                ->where('assignments.tenant_id', $context->id())
                ->where('assignments.event_id', $event->id)
                ->orderByDesc('assignments.status')
                ->orderBy('users.display_name')
                ->get(['assignments.*', 'users.name', 'users.display_name', 'users.email'])
            : collect();
        $staffCandidates = $canManageEvent ? $access->assignableUsers($event) : collect();
        $promos = $canManageEvent
            ? DB::table('promo_codes')->where('tenant_id', $context->id())->where('event_id', $event->id)->orderByDesc('active')->orderByDesc('id')->get()
            : collect();

        return view('tenant.manage.events.operations', [
            'event' => $event,
            'tenant' => $context->requireTenant(),
            'capabilities' => $caps,
            'rsvps' => $rsvps,
            'canManageEvent' => $canManageEvent,
            'eventStaffAssignments' => $staffAssignments,
            'eventStaffCandidates' => $staffCandidates,
            'eventPromos' => $promos,
            'guestPolicyLabel' => $guestPolicy->label($event),
        ]);
    }

    public function message(Request $request, TenantContext $context, Event $event, EventStaffAccessService $access, CommunicationsService $communications)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $access->assertCan($request->user(), $event, EventStaffAccessService::CAP_COMMUNICATIONS);
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
            'delivery' => 'required|in:now,after_event,custom',
            'scheduled_at' => 'nullable|required_if:delivery,custom|date|after:now',
        ]);

        $segmentName = 'Event '.$event->id.' approved attendees';
        $segmentId = DB::table('marketing_segments')->where('tenant_id', $context->id())->where('name', $segmentName)->value('id');
        $segmentPayload = [
            'tenant_id' => $context->id(),
            'created_by' => $request->user()->id,
            'name' => $segmentName,
            'description' => 'Operational audience for approved attendees of '.$event->title.'.',
            'definition' => json_encode(['type' => CommunicationsService::SEGMENT_EVENT_ATTENDEES, 'target_id' => $event->id], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'updated_at' => now(),
        ];
        if ($segmentId) {
            DB::table('marketing_segments')->where('id', $segmentId)->update($segmentPayload);
        } else {
            $segmentPayload['created_at'] = now();
            $segmentId = DB::table('marketing_segments')->insertGetId($segmentPayload);
        }

        $scheduledAt = match ($data['delivery']) {
            'after_event' => ($event->ends_at ?: $event->starts_at)->copy()->addHours(2),
            'custom' => $data['scheduled_at'],
            default => null,
        };
        if ($data['delivery'] === 'after_event' && $scheduledAt->isPast()) $scheduledAt = now()->addMinute();
        $status = $scheduledAt ? 'scheduled' : 'queued';

        $campaignId = DB::table('marketing_campaigns')->insertGetId([
            'tenant_id' => $context->id(),
            'created_by' => $request->user()->id,
            'segment_id' => $segmentId,
            'template_id' => null,
            'name' => Str::limit($event->title.' — '.$data['subject'], 160, ''),
            'channel' => 'email',
            'message_type' => CommunicationsService::MESSAGE_OPERATIONAL,
            'status' => $status,
            'audience' => json_encode(['event_id' => $event->id], JSON_THROW_ON_ERROR),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'scheduled_at' => $scheduledAt,
            'recipient_total' => 0,
            'sent_total' => 0,
            'failed_total' => 0,
            'skipped_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Audit::write('event.attendee_message.queued', $event, after: ['campaign_id' => $campaignId, 'status' => $status, 'scheduled_at' => $scheduledAt], tenantId: $context->id(), request: $request);
        if (! $scheduledAt) $communications->process(100);
        return back()->with('status', $scheduledAt ? 'Attendee message scheduled.' : 'Attendee message queued for delivery.');
    }
}
