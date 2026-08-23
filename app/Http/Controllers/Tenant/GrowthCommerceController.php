<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MembershipLevel;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class GrowthCommerceController extends Controller
{
    public function index(TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        return view('tenant.manage.growth', [
            'tenant' => $tenant,
            'membershipLevels' => DB::table('membership_levels')->where('tenant_id', $tenant->id)->orderBy('sort_order')->orderBy('name')->get(),
            'promoters' => DB::table('promoters')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'contacts' => DB::table('marketing_contacts')->where('tenant_id', $tenant->id)->latest('id')->limit(100)->get(),
            'lists' => DB::table('marketing_lists')->where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'campaigns' => DB::table('marketing_campaigns')->where('tenant_id', $tenant->id)->latest('id')->limit(100)->get(),
            'events' => Event::query()->where('tenant_id', $tenant->id)->latest('starts_at')->limit(50)->get(['id', 'title', 'starts_at', 'status']),
            'addons' => DB::table('event_addons')
                ->join('events', 'events.id', '=', 'event_addons.event_id')
                ->where('events.tenant_id', $tenant->id)
                ->select('event_addons.*', 'events.title as event_title')
                ->orderBy('events.starts_at')->orderBy('event_addons.sort_order')->get(),
        ]);
    }

    public function storeMembership(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $this->membershipData($request);
        $code = $this->membershipCode((string) ($data['code'] ?? ''), (string) $data['name']);
        $this->assertMembershipCodeAvailable((int) $tenant->id, $code);

        MembershipLevel::create($this->membershipPayload($request, $data, (int) $tenant->id, $code));

        return back()->with('status', 'Membership level created.');
    }

    public function updateMembership(Request $request, int $membershipLevel, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $level = MembershipLevel::query()->where('tenant_id', $tenant->id)->findOrFail($membershipLevel);
        $data = $this->membershipData($request);
        $code = $this->membershipCode((string) ($data['code'] ?? ''), (string) $data['name']);
        $this->assertMembershipCodeAvailable((int) $tenant->id, $code, (int) $level->id);
        $level->update($this->membershipPayload($request, $data, (int) $tenant->id, $code));

        return back()->with('status', 'Membership level updated.');
    }

    public function storePromoter(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $this->promoterData($request);
        $code = Str::slug((string) ($data['code'] ?: $data['name']));
        if ($code === '' || DB::table('promoters')->where('tenant_id', $tenant->id)->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Choose a unique promoter code.']);
        }

        DB::table('promoters')->insert([
            'tenant_id' => $tenant->id,
            'name' => trim($data['name']),
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'code' => $code,
            'status' => $data['status'],
            'commission_type' => $data['commission_type'],
            'commission_value' => (int) $data['commission_value'],
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Promoter created.');
    }

    public function updatePromoter(Request $request, int $promoter, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $existing = DB::table('promoters')->where('tenant_id', $tenant->id)->where('id', $promoter)->first();
        abort_unless($existing, 404);
        $data = $this->promoterData($request);
        $code = Str::slug((string) ($data['code'] ?: $data['name']));
        if ($code === '' || DB::table('promoters')->where('tenant_id', $tenant->id)->where('code', $code)->where('id', '!=', $promoter)->exists()) {
            throw ValidationException::withMessages(['code' => 'Choose a unique promoter code.']);
        }

        DB::table('promoters')->where('id', $promoter)->update([
            'name' => trim($data['name']),
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'code' => $code,
            'status' => $data['status'],
            'commission_type' => $data['commission_type'],
            'commission_value' => (int) $data['commission_value'],
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Promoter updated.');
    }

    public function storeContact(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email_opt_in' => ['nullable', 'boolean'],
            'sms_opt_in' => ['nullable', 'boolean'],
        ]);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $emailOptIn = $request->boolean('email_opt_in');
        $smsOptIn = $request->boolean('sms_opt_in');
        if ($email === '' && $phone === '') {
            throw ValidationException::withMessages(['email' => 'Provide an email address or phone number.']);
        }
        if ($emailOptIn && $email === '') {
            throw ValidationException::withMessages(['email' => 'Email consent requires an email address.']);
        }
        if ($smsOptIn && $phone === '') {
            throw ValidationException::withMessages(['phone' => 'SMS consent requires a phone number.']);
        }

        DB::table('marketing_contacts')->insert([
            'tenant_id' => $tenant->id,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'first_name' => trim((string) ($data['first_name'] ?? '')) ?: null,
            'last_name' => trim((string) ($data['last_name'] ?? '')) ?: null,
            'source' => 'manual',
            'status' => 'active',
            'email_opt_in' => $emailOptIn,
            'sms_opt_in' => $smsOptIn,
            'email_consented_at' => $emailOptIn ? now() : null,
            'sms_consented_at' => $smsOptIn ? now() : null,
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Marketing contact added with the selected consent settings.');
    }

    public function storeList(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
        $name = trim($data['name']);
        if (DB::table('marketing_lists')->where('tenant_id', $tenant->id)->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'A marketing list with this name already exists.']);
        }
        DB::table('marketing_lists')->insert([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Marketing list created.');
    }

    public function storeCampaign(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'marketing_list_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'channel' => ['required', 'in:email,sms'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'status' => ['required', 'in:draft,scheduled'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        if ($data['channel'] === 'email' && trim((string) ($data['subject'] ?? '')) === '') {
            throw ValidationException::withMessages(['subject' => 'Email campaigns require a subject.']);
        }
        if ($data['status'] === 'scheduled' && empty($data['scheduled_at'])) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a delivery time for a scheduled campaign.']);
        }
        $listId = isset($data['marketing_list_id']) ? (int) $data['marketing_list_id'] : null;
        if ($listId && ! DB::table('marketing_lists')->where('tenant_id', $tenant->id)->where('id', $listId)->exists()) {
            abort(404);
        }

        DB::table('marketing_campaigns')->insert([
            'tenant_id' => $tenant->id,
            'marketing_list_id' => $listId ?: null,
            'name' => trim($data['name']),
            'channel' => $data['channel'],
            'subject' => trim((string) ($data['subject'] ?? '')) ?: null,
            'body' => trim($data['body']),
            'status' => $data['status'],
            'scheduled_at' => $data['status'] === 'scheduled' ? $data['scheduled_at'] : null,
            'recipient_count' => 0,
            'delivered_count' => 0,
            'open_count' => 0,
            'click_count' => 0,
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Campaign saved. Delivery remains queued for the campaign worker.');
    }

    public function storeAddon(Request $request, Event $event, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $event->tenant_id === (int) $tenant->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'type' => ['required', 'in:food,drink,parking,vip,merchandise,other'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'member_only' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        DB::table('event_addons')->insert([
            'event_id' => $event->id,
            'name' => trim($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'type' => $data['type'],
            'price_cents' => $this->moneyToCents($data['price']),
            'currency' => strtoupper($data['currency']),
            'quantity' => $data['quantity'] ?? null,
            'max_per_order' => (int) $data['max_per_order'],
            'member_only' => $request->boolean('member_only'),
            'active' => $request->boolean('active', true),
            'sort_order' => (int) $data['sort_order'],
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Event add-on created.');
    }

    public function destroyAddon(Event $event, int $addon, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $event->tenant_id === (int) $tenant->id, 404);
        $deleted = DB::table('event_addons')->where('event_id', $event->id)->where('id', $addon)->delete();
        abort_unless($deleted === 1, 404);

        return back()->with('status', 'Event add-on removed.');
    }

    /** @return array<string,mixed> */
    private function membershipData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', 'in:monthly,quarterly,annual,lifetime'],
            'ticket_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'application_required' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function membershipPayload(Request $request, array $data, int $tenantId, string $code): array
    {
        $benefits = collect(preg_split('/\r\n|\r|\n/', (string) ($data['benefits'] ?? '')))
            ->map(fn ($value) => trim((string) $value))->filter()->unique()->take(50)->values()->all();

        return [
            'tenant_id' => $tenantId,
            'name' => trim($data['name']),
            'code' => $code,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'price_cents' => $this->moneyToCents($data['price']),
            'currency' => strtoupper($data['currency']),
            'billing_interval' => $data['billing_interval'],
            'application_required' => $request->boolean('application_required'),
            'ticket_discount_percent' => (int) $data['ticket_discount_percent'],
            'active' => $request->boolean('active'),
            'sort_order' => (int) $data['sort_order'],
            'benefits' => $benefits,
        ];
    }

    /** @return array<string,mixed> */
    private function promoterData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'code' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:active,paused,disabled'],
            'commission_type' => ['required', 'in:percent,fixed'],
            'commission_value' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);
    }

    private function membershipCode(string $code, string $name): string
    {
        return Str::slug($code !== '' ? $code : $name);
    }

    private function assertMembershipCodeAvailable(int $tenantId, string $code, ?int $exceptId = null): void
    {
        $query = MembershipLevel::query()->where('tenant_id', $tenantId)->where('code', $code);
        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }
        if ($code === '' || $query->exists()) {
            throw ValidationException::withMessages(['code' => 'Choose a unique membership code.']);
        }
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
