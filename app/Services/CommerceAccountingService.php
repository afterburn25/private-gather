<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CommerceAccountingService
{
    private const RESERVED_REFUND_STATUSES = ['requested', 'processing', 'completed', 'manual_required'];
    private const RESERVED_PAYOUT_STATUSES = ['requested', 'processing', 'paid'];

    public function availablePayoutCents(int $tenantId): int
    {
        $this->reconcileRefundAllocations($tenantId);

        $earned = (int) DB::table('marketplace_ledger_entries')
            ->where('tenant_id', $tenantId)
            ->where('status', 'posted')
            ->sum('tenant_net_cents');
        $payouts = (int) DB::table('payouts')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::RESERVED_PAYOUT_STATUSES)
            ->sum('amount_cents');
        $refundNet = (int) DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::RESERVED_REFUND_STATUSES)
            ->sum('tenant_net_reversed_cents');

        return max(0, $earned - $payouts - $refundNet);
    }

    public function refundableCents(Order $order): int
    {
        $reserved = (int) DB::table('refunds')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->whereIn('status', self::RESERVED_REFUND_STATUSES)
            ->sum('amount_cents');

        return max(0, (int) $order->total_cents - $reserved);
    }

    public function reservePayout(Tenant $tenant, int $requestedBy, int $amountCents): int
    {
        return DB::transaction(function () use ($tenant, $requestedBy, $amountCents): int {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $available = $this->availablePayoutCents((int) $tenant->id);
            if ($amountCents > $available) {
                throw new HttpException(422, 'Requested payout exceeds the available balance.');
            }

            return (int) DB::table('payouts')->insertGetId([
                'tenant_id' => $tenant->id,
                'requested_by' => $requestedBy,
                'amount_cents' => $amountCents,
                'currency' => 'USD',
                'status' => 'requested',
                'status_reason' => null,
                'scheduled_at' => now(),
                'requested_at' => now(),
                'paid_at' => null,
                'metadata' => json_encode(['available_before_cents' => $available], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function reserveRefund(Order $order, int $requestedBy, int $amountCents, ?string $reason, ProductCompletionService $products): int
    {
        return DB::transaction(function () use ($order, $requestedBy, $amountCents, $reason, $products): int {
            Tenant::query()->whereKey($order->tenant_id)->lockForUpdate()->firstOrFail();
            $locked = Order::query()
                ->whereKey($order->id)
                ->where('tenant_id', $order->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array((string) $locked->status, ['paid', 'completed'], true)) {
                throw new HttpException(404, 'Paid order not found.');
            }

            $products->recordPaidOrder($locked);
            $this->reconcileRefundAllocations((int) $locked->tenant_id);
            if ($amountCents > $this->refundableCents($locked)) {
                throw new HttpException(422, 'Refund amount exceeds the refundable order balance.');
            }

            $allocation = $this->nextRefundAllocation($locked, $amountCents);

            return (int) DB::table('refunds')->insertGetId([
                'tenant_id' => $locked->tenant_id,
                'requested_by' => $requestedBy,
                'order_id' => $locked->id,
                'payment_id' => $locked->payments()->where('status', 'paid')->value('id'),
                'amount_cents' => $amountCents,
                'platform_fee_reversed_cents' => $allocation['platform_fee_reversed_cents'],
                'tenant_net_reversed_cents' => $allocation['tenant_net_reversed_cents'],
                'status' => 'requested',
                'provider_reference' => null,
                'processed_at' => null,
                'reason' => $reason,
                'metadata' => json_encode(['allocation' => $allocation], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function finalizeRefund(int $refundId, array $providerResult): void
    {
        $providerStatus = strtolower(trim((string) ($providerResult['status'] ?? 'manual_required')));
        $status = match ($providerStatus) {
            'refunded', 'completed' => 'completed',
            'processing', 'pending' => 'processing',
            'failed', 'declined', 'rejected', 'cancelled' => 'failed',
            default => 'manual_required',
        };

        DB::table('refunds')->where('id', $refundId)->update([
            'status' => $status,
            'provider_reference' => $providerResult['reference'] ?? null,
            'processed_at' => now(),
            'metadata' => json_encode($providerResult, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function reconcileRefundAllocations(int $tenantId): void
    {
        $orderIds = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::RESERVED_REFUND_STATUSES)
            ->distinct()
            ->pluck('order_id');

        foreach ($orderIds as $orderId) {
            $sale = DB::table('marketplace_ledger_entries')
                ->where('tenant_id', $tenantId)
                ->where('order_id', $orderId)
                ->where('type', 'ticket_sale')
                ->where('status', 'posted')
                ->first();
            if (! $sale || (int) $sale->gross_cents <= 0) {
                continue;
            }

            $refunds = DB::table('refunds')
                ->where('tenant_id', $tenantId)
                ->where('order_id', $orderId)
                ->whereIn('status', self::RESERVED_REFUND_STATUSES)
                ->orderBy('id')
                ->get();

            $cumulativeGross = 0;
            $cumulativeFee = 0;
            $cumulativeNet = 0;
            foreach ($refunds as $refund) {
                $targetGross = min((int) $sale->gross_cents, $cumulativeGross + (int) $refund->amount_cents);
                $targetFee = $this->cumulativeFeeReversal((int) $sale->gross_cents, (int) $sale->platform_fee_cents, $targetGross);
                $targetNet = $targetGross - $targetFee;
                $feeDelta = max(0, $targetFee - $cumulativeFee);
                $netDelta = max(0, $targetNet - $cumulativeNet);

                if ((int) $refund->platform_fee_reversed_cents !== $feeDelta || (int) $refund->tenant_net_reversed_cents !== $netDelta) {
                    DB::table('refunds')->where('id', $refund->id)->update([
                        'platform_fee_reversed_cents' => $feeDelta,
                        'tenant_net_reversed_cents' => $netDelta,
                        'updated_at' => now(),
                    ]);
                }

                $cumulativeGross = $targetGross;
                $cumulativeFee = $targetFee;
                $cumulativeNet = $targetNet;
            }
        }
    }

    private function nextRefundAllocation(Order $order, int $amountCents): array
    {
        $sale = DB::table('marketplace_ledger_entries')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->where('type', 'ticket_sale')
            ->where('status', 'posted')
            ->first();
        if (! $sale || (int) $sale->gross_cents <= 0) {
            return [
                'gross_cents' => $amountCents,
                'platform_fee_reversed_cents' => 0,
                'tenant_net_reversed_cents' => $amountCents,
            ];
        }

        $alreadyGross = (int) DB::table('refunds')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->whereIn('status', self::RESERVED_REFUND_STATUSES)
            ->sum('amount_cents');
        $alreadyFee = (int) DB::table('refunds')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->whereIn('status', self::RESERVED_REFUND_STATUSES)
            ->sum('platform_fee_reversed_cents');
        $alreadyNet = (int) DB::table('refunds')
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->id)
            ->whereIn('status', self::RESERVED_REFUND_STATUSES)
            ->sum('tenant_net_reversed_cents');

        $targetGross = min((int) $sale->gross_cents, $alreadyGross + $amountCents);
        $targetFee = $this->cumulativeFeeReversal((int) $sale->gross_cents, (int) $sale->platform_fee_cents, $targetGross);
        $targetNet = $targetGross - $targetFee;

        return [
            'gross_cents' => $amountCents,
            'platform_fee_reversed_cents' => max(0, $targetFee - $alreadyFee),
            'tenant_net_reversed_cents' => max(0, $targetNet - $alreadyNet),
        ];
    }

    private function cumulativeFeeReversal(int $saleGross, int $saleFee, int $refundedGross): int
    {
        if ($saleGross <= 0 || $saleFee <= 0 || $refundedGross <= 0) {
            return 0;
        }
        if ($refundedGross >= $saleGross) {
            return $saleFee;
        }

        return max(0, min($saleFee, (int) round($saleFee * ($refundedGross / $saleGross))));
    }
}
