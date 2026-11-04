<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CommunicationsService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CommunicationsController extends Controller
{
    public function index(TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        return view('tenant.manage.communications', [
            'tenant' => $tenant,
            'segments' => DB::table('marketing_segments')->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(),
            'templates' => DB::table('marketing_templates')->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(),
            'campaigns' => DB::table('marketing_campaigns')->where('tenant_id', $tenant->id)->latest('id')->limit(100)->get(),
            'chapters' => DB::table('community_groups')->where('tenant_id', $tenant->id)->where('group_type', 'chapter')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'levels' => DB::table('tenant_membership_levels')->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'events' => DB::table('events')->where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'published'])->orderByDesc('starts_at')->limit(100)->get(['id', 'title', 'starts_at']),
            'tags' => DB::table('tenant_member_tags')->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeSegment(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'segment_type' => ['required', 'in:all_opted_in,all_members,chapter,membership_level,event_attendees,crm_tag'],
            'target_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $definition = ['type' => $data['segment_type']];
        if (! in_array($data['segment_type'], ['all_opted_in', 'all_members'], true)) {
            if (empty($data['target_id'])) throw ValidationException::withMessages(['target_id' => 'Choose the chapter, membership level, event or CRM tag for this segment.']);
            $this->assertSegmentTarget($tenant->id, $data['segment_type'], (int) $data['target_id']);
            $definition['target_id'] = (int) $data['target_id'];
        }

        DB::table('marketing_segments')->insert([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'definition' => json_encode($definition, JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Audit::write('communications.segment.created', null, after: ['name' => $data['name'], 'definition' => $definition], tenantId: $tenant->id, request: $request);

        return back()->with('status', 'Reusable audience segment created.');
    }

    public function storeTemplate(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'message_type' => ['required', 'in:marketing,operational'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:30000'],
        ]);

        DB::table('marketing_templates')->insert([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
            'name' => trim($data['name']),
            'channel' => 'email',
            'message_type' => $data['message_type'],
            'subject' => trim($data['subject']),
            'body' => $data['body'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Audit::write('communications.template.created', null, after: ['name' => $data['name'], 'message_type' => $data['message_type']], tenantId: $tenant->id, request: $request);

        return back()->with('status', 'Communications template saved.');
    }

    public function storeCampaign(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'message_type' => ['required', 'in:marketing,operational'],
            'segment_id' => ['nullable', 'integer', 'min:1'],
            'template_id' => ['nullable', 'integer', 'min:1'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:30000'],
            'scheduled_at' => ['nullable', 'date'],
            'action' => ['required', 'in:draft,queue'],
        ]);

        $segment = null;
        if (! empty($data['segment_id'])) {
            $segment = DB::table('marketing_segments')->where('tenant_id', $tenant->id)->where('id', $data['segment_id'])->where('is_active', true)->first();
            abort_unless($segment, 404);
            $definition = json_decode((string) $segment->definition, true) ?: [];
            if ($data['message_type'] === CommunicationsService::MESSAGE_OPERATIONAL && ($definition['type'] ?? null) === CommunicationsService::SEGMENT_ALL_OPTED_IN) {
                throw ValidationException::withMessages(['segment_id' => 'Operational notices cannot use the marketing-only all-opted-in audience. Choose active members or a member subset.']);
            }
        }

        $template = null;
        if (! empty($data['template_id'])) {
            $template = DB::table('marketing_templates')->where('tenant_id', $tenant->id)->where('id', $data['template_id'])->where('is_active', true)->first();
            abort_unless($template, 404);
            if ($template->message_type !== $data['message_type']) {
                throw ValidationException::withMessages(['template_id' => 'Choose a template with the same marketing or operational classification as the campaign.']);
            }
        }

        $subject = trim((string) ($data['subject'] ?? '')) ?: trim((string) ($template->subject ?? ''));
        $body = trim((string) ($data['body'] ?? '')) ?: (string) ($template->body ?? '');
        if ($subject === '') throw ValidationException::withMessages(['subject' => 'Email subject is required.']);
        if (trim($body) === '') throw ValidationException::withMessages(['body' => 'Email body is required.']);

        $status = 'draft';
        if ($data['action'] === 'queue') {
            $status = ! empty($data['scheduled_at']) && now()->lt($data['scheduled_at']) ? 'scheduled' : 'queued';
        }

        $id = DB::table('marketing_campaigns')->insertGetId([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
            'segment_id' => $segment?->id,
            'template_id' => $template?->id,
            'name' => trim($data['name']),
            'channel' => 'email',
            'message_type' => $data['message_type'],
            'status' => $status,
            'audience' => json_encode(['segment_id' => $segment?->id, 'consent_required' => $data['message_type'] === CommunicationsService::MESSAGE_MARKETING], JSON_THROW_ON_ERROR),
            'subject' => $subject,
            'body' => $body,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'sent_at' => null,
            'processing_started_at' => null,
            'stats' => json_encode(['eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0], JSON_THROW_ON_ERROR),
            'recipient_total' => 0,
            'sent_total' => 0,
            'failed_total' => 0,
            'skipped_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Audit::write('communications.campaign.created', null, after: ['campaign_id' => $id, 'status' => $status, 'message_type' => $data['message_type'], 'segment_id' => $segment?->id], tenantId: $tenant->id, request: $request);

        return back()->with('status', $status === 'draft' ? 'Campaign draft saved.' : ($status === 'scheduled' ? 'Campaign scheduled.' : 'Campaign queued for consent-aware delivery.'));
    }

    public function queueCampaign(Request $request, TenantContext $context, int $campaign): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $row = DB::table('marketing_campaigns')->where('tenant_id', $tenant->id)->where('id', $campaign)->first();
        abort_unless($row, 404);
        abort_unless($row->status === 'draft', 422, 'Only draft campaigns can be queued manually.');
        abort_if(DB::table('marketing_campaign_recipients')->where('campaign_id', $row->id)->exists(), 422, 'This campaign already has a frozen recipient history and cannot be re-queued as a draft.');

        $status = $row->scheduled_at && now()->lt($row->scheduled_at) ? 'scheduled' : 'queued';
        DB::table('marketing_campaigns')->where('id', $row->id)->update(['status' => $status, 'updated_at' => now()]);
        Audit::write('communications.campaign.queued', null, after: ['campaign_id' => $row->id, 'status' => $status], tenantId: $tenant->id, request: $request);

        return back()->with('status', $status === 'scheduled' ? 'Campaign scheduled.' : 'Campaign queued.');
    }

    private function assertSegmentTarget(int $tenantId, string $type, int $targetId): void
    {
        $valid = match ($type) {
            'chapter' => DB::table('community_groups')->where('tenant_id', $tenantId)->where('id', $targetId)->where('group_type', 'chapter')->where('status', 'active')->exists(),
            'membership_level' => DB::table('tenant_membership_levels')->where('tenant_id', $tenantId)->where('id', $targetId)->where('is_active', true)->exists(),
            'event_attendees' => DB::table('events')->where('tenant_id', $tenantId)->where('id', $targetId)->exists(),
            'crm_tag' => DB::table('tenant_member_tags')->where('tenant_id', $tenantId)->where('id', $targetId)->exists(),
            default => false,
        };
        if (!$valid) throw ValidationException::withMessages(['target_id' => 'The selected audience target does not belong to this organization or is not active.']);
    }
}
