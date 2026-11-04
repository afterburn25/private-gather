<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CommerceMerchantController extends Controller
{
    public function update(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'provider_account_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = DB::table('merchant_accounts')->where('tenant_id', $tenant->id)->first();
        if ($existing && in_array((string) $existing->status, ['connected', 'restricted'], true)) {
            abort(422, 'Provider-confirmed merchant state cannot be overwritten manually. Use the provider connection workflow when available.');
        }

        $provider = strtolower(trim((string) $data['provider']));
        $accountRef = trim((string) ($data['provider_account_ref'] ?? ''));
        $status = $provider === 'manual' || $accountRef === '' ? 'not_connected' : 'pending';

        DB::table('merchant_accounts')->updateOrInsert(
            ['tenant_id' => $tenant->id],
            [
                'provider' => $provider,
                'provider_account_ref' => $accountRef !== '' ? $accountRef : null,
                'status' => $status,
                'capabilities' => json_encode([
                    'ticket_sales' => false,
                    'membership_billing' => false,
                    'split_ledger' => true,
                    'provider_confirmation_required' => true,
                ], JSON_THROW_ON_ERROR),
                'onboarded_at' => null,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        Audit::write('commerce.merchant.configuration_saved', tenantId: (int) $tenant->id, request: $request, after: [
            'provider' => $provider,
            'provider_account_ref_present' => $accountRef !== '',
            'status' => $status,
        ]);

        return back()->with('status', $status === 'pending'
            ? 'Merchant provider configuration saved as pending. Private Gather will not call it connected until a provider-confirmed onboarding flow succeeds.'
            : 'Merchant provider configuration saved. No live payment connection is claimed.');
    }
}
