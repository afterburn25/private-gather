<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketManageController extends Controller
{
    public function store(Request $request, TenantContext $context, Event $event)
    {
        abort_unless($event->tenant_id === $context->id(), 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'profile_eligibility' => 'required|in:any,couple,individual',
            'membership_required' => 'nullable|boolean',
            'approval_required' => 'nullable|boolean',
            'price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'max_per_order' => 'required|integer|min:1|max:100',
        ]);

        $type = $event->ticketTypes()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'profile_eligibility' => $data['profile_eligibility'],
            'membership_required' => $request->boolean('membership_required'),
            'approval_required' => $request->boolean('approval_required'),
            'price_cents' => (int) round(((float) $data['price']) * 100),
            'quantity' => $data['quantity'] ?? null,
            'max_per_order' => $data['max_per_order'],
            'currency' => 'USD',
            'active' => true,
        ]);

        Audit::write('event.ticket_type.created', $type, after: $type->toArray(), tenantId: $context->id(), request: $request);

        $label = match ($data['profile_eligibility']) {
            TicketType::ELIGIBILITY_COUPLE => 'couple-profile',
            TicketType::ELIGIBILITY_INDIVIDUAL => 'individual-profile',
            default => 'all-profile',
        };

        return back()->with('status', 'Ticket type created with '.$label.' eligibility.');
    }

    public function storePromo(Request $request, TenantContext $context, Event $event)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'discount_kind' => ['required', 'in:percent,fixed,comp'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $code = strtoupper(trim($data['code']));
        abort_if(DB::table('promo_codes')->where('tenant_id', $event->tenant_id)->whereRaw('UPPER(code) = ?', [$code])->exists(), 422, 'That promo code already exists for this organization.');

        $kind = $data['discount_kind'];
        if ($kind === 'comp') {
            $discountType = 'percent';
            $discountValue = 100;
        } elseif ($kind === 'percent') {
            $discountType = 'percent';
            $discountValue = (int) round((float) ($data['discount_value'] ?? 0));
            abort_if($discountValue < 1 || $discountValue > 100, 422, 'Percentage discounts must be between 1 and 100.');
        } else {
            $discountType = 'fixed';
            $discountValue = (int) round(((float) ($data['discount_value'] ?? 0)) * 100);
            abort_if($discountValue < 1, 422, 'Fixed discounts must be at least $0.01.');
        }

        $id = DB::table('promo_codes')->insertGetId([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'max_uses' => $data['max_uses'] ?? null,
            'uses' => 0,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Audit::write('event.promo.created', null, after: [
            'promo_id' => $id,
            'event_id' => $event->id,
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ], tenantId: $context->id(), request: $request);

        return back()->with('status', $kind === 'comp' ? 'Comp code created.' : 'Promo code created.');
    }

    public function deactivatePromo(Request $request, TenantContext $context, Event $event, int $promo)
    {
        abort_unless((int) $event->tenant_id === (int) $context->id(), 404);
        $row = DB::table('promo_codes')->where('tenant_id', $event->tenant_id)->where('event_id', $event->id)->where('id', $promo)->first();
        abort_unless($row, 404);
        DB::table('promo_codes')->where('id', $row->id)->update(['active' => false, 'updated_at' => now()]);
        Audit::write('event.promo.deactivated', null, before: ['active' => (bool) $row->active], after: ['promo_id' => $row->id, 'active' => false], tenantId: $context->id(), request: $request);
        return back()->with('status', 'Promo/comp code deactivated. Existing paid redemptions remain in order history.');
    }
}
