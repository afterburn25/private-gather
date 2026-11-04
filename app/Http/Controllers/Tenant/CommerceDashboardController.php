<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TenantMembershipLevel;
use App\Services\CommerceAccountingService;
use App\Services\ProductCompletionService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommerceDashboardController extends Controller
{
    public function __invoke(TenantContext $context, ProductCompletionService $products, CommerceAccountingService $accounting): View
    {
        $tenant = $context->requireTenant();

        // Reconcile historical paid orders idempotently before presenting money
        // balances. The unique order/type ledger key prevents duplicate posting.
        Order::where('tenant_id', $tenant->id)
            ->whereIn('status', ['paid', 'completed'])
            ->with(['payments', 'tenant'])
            ->chunkById(100, fn ($orders) => $orders->each(fn ($order) => $products->recordPaidOrder($order)));
        $accounting->reconcileRefundAllocations((int) $tenant->id);

        $gross = (int) DB::table('marketplace_ledger_entries')->where('tenant_id', $tenant->id)->where('status', 'posted')->sum('gross_cents');
        $fees = (int) DB::table('marketplace_ledger_entries')->where('tenant_id', $tenant->id)->where('status', 'posted')->sum('platform_fee_cents');
        $net = (int) DB::table('marketplace_ledger_entries')->where('tenant_id', $tenant->id)->where('status', 'posted')->sum('tenant_net_cents');
        $orders = Order::where('tenant_id', $tenant->id)->whereIn('status', ['paid', 'completed'])->with(['user', 'event'])->latest()->limit(40)->get();
        $refundable = $orders->mapWithKeys(fn (Order $order) => [$order->id => $accounting->refundableCents($order)]);

        return view('tenant.manage.commerce', [
            'tenant' => $tenant,
            'merchant' => DB::table('merchant_accounts')->where('tenant_id', $tenant->id)->first(),
            'ledger' => DB::table('marketplace_ledger_entries')->where('tenant_id', $tenant->id)->latest()->paginate(40, ['*'], 'ledger_page'),
            'payouts' => DB::table('payouts')
                ->leftJoin('users as payout_requesters', 'payout_requesters.id', '=', 'payouts.requested_by')
                ->where('payouts.tenant_id', $tenant->id)
                ->select('payouts.*', 'payout_requesters.name as requester_name', 'payout_requesters.display_name as requester_display_name')
                ->latest('payouts.id')->limit(30)->get(),
            'refunds' => DB::table('refunds')
                ->leftJoin('users as refund_requesters', 'refund_requesters.id', '=', 'refunds.requested_by')
                ->where('refunds.tenant_id', $tenant->id)
                ->select('refunds.*', 'refund_requesters.name as requester_name', 'refund_requesters.display_name as requester_display_name')
                ->latest('refunds.id')->limit(30)->get(),
            'available' => $accounting->availablePayoutCents((int) $tenant->id),
            'gross' => $gross,
            'fees' => $fees,
            'net' => $net,
            'subscriptions' => DB::table('membership_subscriptions')
                ->leftJoin('users', 'users.id', '=', 'membership_subscriptions.user_id')
                ->leftJoin('tenant_membership_levels', 'tenant_membership_levels.id', '=', 'membership_subscriptions.membership_level_id')
                ->where('membership_subscriptions.tenant_id', $tenant->id)
                ->select('membership_subscriptions.*', 'users.name as user_name', 'users.display_name', 'tenant_membership_levels.name as level_name')
                ->latest('membership_subscriptions.id')->limit(100)->get(),
            'levels' => TenantMembershipLevel::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order')->get(),
            'members' => $tenant->users()->wherePivot('status', 'active')->orderByRaw('COALESCE(display_name, name)')->get(['users.id', 'users.name', 'users.display_name']),
            'orders' => $orders,
            'refundable' => $refundable,
            'refundNetReserved' => (int) DB::table('refunds')->where('tenant_id', $tenant->id)->whereIn('status', ['requested','processing','completed','manual_required'])->sum('tenant_net_reversed_cents'),
            'payoutReserved' => (int) DB::table('payouts')->where('tenant_id', $tenant->id)->whereIn('status', ['requested','processing','paid'])->sum('amount_cents'),
        ]);
    }
}
