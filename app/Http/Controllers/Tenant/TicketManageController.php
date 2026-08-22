<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Event;
use App\Models\TicketType;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class TicketManageController
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

        $event->ticketTypes()->create([
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

        $label = match ($data['profile_eligibility']) {
            TicketType::ELIGIBILITY_COUPLE => 'couple-profile',
            TicketType::ELIGIBILITY_INDIVIDUAL => 'individual-profile',
            default => 'all-profile',
        };

        return back()->with('status', 'Ticket type created with '.$label.' eligibility.');
    }
}
