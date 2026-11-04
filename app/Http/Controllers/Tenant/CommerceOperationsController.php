<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CommerceAccountingService;
use App\Services\ProductCompletionService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommerceOperationsController extends Controller
{
    public function payout(Request $request, TenantContext $context, CommerceAccountingService $accounting): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $amount = (int) $request->validate([
            'amount_cents' => ['required', 'integer', 'min:100'],
        ])['amount_cents'];

        $payoutId = $accounting->reservePayout($tenant, (int) $request->user()->id, $amount);
        Audit::write('commerce.payout.requested', tenantId: (int) $tenant->id, request: $request, after: [
            'payout_id' => $payoutId,
            'amount_cents' => $amount,
            'status' => 'requested',
        ]);

        return back()->with('status', 'Payout request reserved from the available club balance. Provider settlement remains pending until confirmed.');
    }

    public function refund(
        Request $request,
        TenantContext $context,
        Order $order,
        PaymentGateway $gateway,
        ProductCompletionService $products,
        CommerceAccountingService $accounting,
    ): RedirectResponse {
        $tenant = $context->requireTenant();
        abort_unless((int) $order->tenant_id === (int) $tenant->id && in_array((string) $order->status, ['paid', 'completed'], true), 404);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $amount = (int) $data['amount_cents'];
        $reason = isset($data['reason']) ? trim((string) $data['reason']) : null;
        $refundId = $accounting->reserveRefund($order, (int) $request->user()->id, $amount, $reason ?: null, $products);

        try {
            $result = $gateway->refund($order->fresh(), $amount);
        } catch (\Throwable) {
            // A transport failure can leave provider state ambiguous. Keep the
            // money reserved and require review rather than falsely releasing it.
            $result = [
                'status' => 'manual_required',
                'message' => 'The payment provider response could not be confirmed. Manual review is required.',
            ];
        }
        $accounting->finalizeRefund($refundId, $result);
        $status = strtolower(trim((string) ($result['status'] ?? 'manual_required')));

        Audit::write('commerce.refund.requested', tenantId: (int) $tenant->id, request: $request, after: [
            'refund_id' => $refundId,
            'order_id' => (int) $order->id,
            'amount_cents' => $amount,
            'provider_status' => $status,
        ]);

        $message = match ($status) {
            'refunded', 'completed' => 'Refund completed and the club/platform ledger reversal is reserved.',
            'processing', 'pending' => 'Refund is processing. The corresponding club balance remains reserved.',
            'failed', 'declined', 'rejected', 'cancelled' => 'Refund was rejected by the provider. The failed request no longer reduces available payout balance.',
            default => 'Refund requires manual review. The corresponding club balance remains reserved until resolved.',
        };

        return back()->with('status', $message);
    }
}
