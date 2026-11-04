@extends('layouts.app')

@section('title', 'Commerce & Payouts · '.$tenant->name)

@push('head')
<link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/product-completion.css') }}">
@endpush

@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">CLUB OS · COMMERCE</span>
        <h1>Money in, fees clear, access accounted for.</h1>
        <p>Ticket sales, platform fees, membership billing state, refunds and payouts share one tenant-scoped ledger. Provider state is explicit—Private Gather never calls a pending external charge paid.</p>
        <nav class="pc-chip-row" aria-label="Club OS">
            <a href="{{ route('tenant.crm.index') }}">CRM</a>
            <a href="{{ route('tenant.commerce.index') }}">Commerce</a>
            <a href="{{ route('tenant.domain-marketplace.index') }}">Domains</a>
            <a href="{{ route('tenant.builder.index') }}">Website Builder</a>
            <a href="{{ route('tenant.growth.index') }}">Growth</a>
            <a href="{{ route('tenant.analytics.index') }}">Analytics</a>
        </nav>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell">
        @if(session('status'))
            <div class="success" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="pg-grid pg-grid-4">
            <div class="pg-stat"><span>Gross processed</span><strong>${{ number_format($gross / 100, 2) }}</strong></div>
            <div class="pg-stat"><span>Original platform fees</span><strong>${{ number_format($fees / 100, 2) }}</strong></div>
            <div class="pg-stat"><span>Original club net</span><strong>${{ number_format($net / 100, 2) }}</strong></div>
            <div class="pg-stat"><span>Available to request</span><strong>${{ number_format($available / 100, 2) }}</strong></div>
        </div>

        <div class="pg-card" style="margin-top:16px">
            <div class="pg-grid pg-grid-2">
                <div>
                    <span class="pg-eyebrow">RESERVED CLUB BALANCE</span>
                    <h3>${{ number_format($refundNetReserved / 100, 2) }} in refund reversals</h3>
                    <p class="muted">Requested, processing, completed and manual-review refunds reserve only the club-net portion. The matching platform fee reversal is tracked separately.</p>
                </div>
                <div>
                    <span class="pg-eyebrow">PAYOUT RESERVATIONS</span>
                    <h3>${{ number_format($payoutReserved / 100, 2) }} reserved</h3>
                    <p class="muted">Requested, processing and paid payouts reserve balance so the same club funds cannot be requested twice.</p>
                </div>
            </div>
            <p class="pg-privacy-note">Available to request = posted club net − reserved club refund reversals − requested/processing/paid payouts. Failed refund requests do not remain reserved.</p>
        </div>

        <section class="pg-section">
            <div class="pc-two">
                <article class="pg-card">
                    <span class="pg-eyebrow">MERCHANT CONNECTION</span>
                    <h2>Provider state</h2>
                    @if($merchant && in_array($merchant->status, ['connected','restricted'], true))
                        <span class="pg-pill">{{ ucfirst($merchant->status) }}</span>
                        <p><strong>{{ $merchant->provider }}</strong>@if($merchant->provider_account_ref) · {{ $merchant->provider_account_ref }}@endif</p>
                        <p class="muted">This is provider-confirmed state and cannot be overwritten manually from Club OS. A future provider connection workflow will handle reauthorization or disconnect requests.</p>
                    @else
                        <form method="post" action="{{ route('tenant.commerce.merchant') }}" class="form-stack">
                            @csrf
                            @method('PATCH')
                            <label>Provider
                                <input name="provider" value="{{ old('provider', $merchant?->provider ?? 'manual') }}" placeholder="Provider adapter name" required>
                            </label>
                            <label>Public account reference
                                <input name="provider_account_ref" value="{{ old('provider_account_ref', $merchant?->provider_account_ref) }}" placeholder="Provider account ID — never a secret key">
                            </label>
                            <button class="button button-primary">Save provider setup</button>
                        </form>
                        <div class="pg-privacy-note">Saving a provider/account reference creates only pending configuration. A club manager cannot mark a payment provider connected. API credentials remain in server configuration and provider confirmation is required before payment capabilities become active.</div>
                    @endif
                </article>

                <form method="post" action="{{ route('tenant.commerce.payouts.store') }}" class="pg-card form-stack">
                    @csrf
                    <span class="pg-eyebrow">PAYOUT</span>
                    <h2>Request available funds</h2>
                    <label>Amount in cents
                        <input name="amount_cents" type="number" min="100" max="{{ $available }}" value="{{ $available }}" required>
                    </label>
                    <button class="button button-primary" @disabled($available < 100)>Request payout</button>
                    <p class="muted">The balance is rechecked inside a database lock when submitted. A simultaneous payout or refund cannot reuse the same available funds. Provider settlement remains pending until confirmed.</p>
                </form>
            </div>
        </section>

        <section class="pg-section">
            <div class="pg-section-head">
                <div>
                    <span class="pg-eyebrow">MEMBERSHIP BILLING</span>
                    <h2>Subscriptions & access terms</h2>
                    <p>Create complimentary terms or prepare recurring memberships for the configured provider.</p>
                </div>
            </div>

            <div class="pc-two">
                <form method="post" action="{{ route('tenant.commerce.subscriptions.store') }}" class="pg-card form-stack">
                    @csrf
                    <label>Member
                        <select name="user_id" required>
                            <option value="">Choose member</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}">{{ $member->display_name ?: $member->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Membership level
                        <select name="membership_level_id" required>
                            <option value="">Choose level</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}">{{ $level->name }} · {{ $level->priceLabel() }} · {{ ucfirst($level->billing_interval) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="row-actions"><input type="checkbox" name="complimentary" value="1"> Complimentary / no external charge</label>
                    <button class="button button-primary" @disabled($members->isEmpty() || $levels->isEmpty())>Create membership term</button>
                    <small class="muted">A paid recurring term remains awaiting/pending provider until the external provider confirms billing. Complimentary/free terms can activate immediately.</small>
                </form>

                <aside class="pg-card">
                    <span class="pg-eyebrow">BILLING BOUNDARY</span>
                    <h2>Access and payment are separate facts.</h2>
                    <p class="muted">Private Gather records the membership term, provider state, amount and current period independently. Membership access stays auditable even when external processing is delayed, restricted or manual.</p>
                    <a class="button button-ghost" href="{{ route('tenant.membership-levels.index') }}">Manage membership levels</a>
                </aside>
            </div>

            <div class="pg-grid" style="margin-top:16px">
                @if($subscriptions->isEmpty())
                    <div class="pg-empty"><h3>No subscription records yet.</h3><p>Approved memberships can still exist without recurring billing.</p></div>
                @else
                    @foreach($subscriptions as $subscription)
                        <article class="pg-card">
                            <div class="row-between">
                                <div>
                                    <span class="pg-pill">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span>
                                    <h3>{{ $subscription->display_name ?: $subscription->user_name ?: 'Member' }} · {{ $subscription->level_name ?: 'Membership' }}</h3>
                                    <p class="muted">
                                        ${{ number_format($subscription->amount_cents / 100, 2) }} · {{ ucfirst($subscription->billing_interval) }} · {{ $subscription->provider }}
                                        @if($subscription->current_period_ends_at)
                                            · through {{ \Carbon\Carbon::parse($subscription->current_period_ends_at)->format('M j, Y') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="pg-actions">
                                    @if($subscription->cancel_at_period_end)
                                        <span class="pg-pill pg-pill-warn">Cancels at period end</span>
                                    @elseif($subscription->status !== 'cancelled')
                                        <form method="post" action="{{ route('tenant.commerce.subscriptions.cancel', $subscription->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button button-ghost" data-confirm="Schedule this subscription to cancel at period end?">Cancel at period end</button>
                                        </form>
                                    @endif
                                    @if(! in_array($subscription->status, ['awaiting_provider','pending_provider'], true))
                                        <form method="post" action="{{ route('tenant.commerce.subscriptions.renew', $subscription->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button button-ghost">Renew term</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                @endif
            </div>
        </section>

        <section class="pg-section">
            <div class="pg-section-head"><div><span class="pg-eyebrow">SPLIT LEDGER</span><h2>Posted ticket-sale accounting</h2></div></div>
            <div class="pg-card" style="overflow:auto">
                <table>
                    <thead><tr><th>Date</th><th>Order</th><th>Gross</th><th>Platform fee</th><th>Club net</th><th>Status</th></tr></thead>
                    <tbody>
                        @if($ledger->isEmpty())
                            <tr><td colspan="6">Paid ticket orders will appear here.</td></tr>
                        @else
                            @foreach($ledger as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('M j, Y') }}</td>
                                    <td>#{{ $row->order_id }}</td>
                                    <td>${{ number_format($row->gross_cents / 100, 2) }}</td>
                                    <td>${{ number_format($row->platform_fee_cents / 100, 2) }}</td>
                                    <td>${{ number_format($row->tenant_net_cents / 100, 2) }}</td>
                                    <td>{{ $row->status }}</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            {{ $ledger->links() }}
        </section>

        <section class="pg-section">
            <div class="pg-section-head">
                <div>
                    <span class="pg-eyebrow">REFUNDS</span>
                    <h2>Paid orders</h2>
                    <p>Refunds are reserved before the provider call, cannot exceed the remaining refundable balance, and reverse the platform fee and club net proportionally.</p>
                </div>
            </div>

            <div class="pg-grid pg-grid-2">
                @if($orders->isEmpty())
                    <div class="pg-empty">No paid orders available for refund.</div>
                @else
                    @foreach($orders as $order)
                        @php($remainingRefundable = (int) ($refundable[$order->id] ?? 0))
                        <article class="pg-card">
                            <span class="pg-pill">Order #{{ $order->id }}</span>
                            <h3>{{ $order->event?->title ?: 'Event order' }}</h3>
                            <p class="muted">{{ $order->user?->display_name ?: $order->user?->name ?: 'Member' }} · paid ${{ number_format($order->total_cents / 100, 2) }} · {{ ucfirst($order->status) }}</p>
                            <p><strong>${{ number_format($remainingRefundable / 100, 2) }} remaining refundable</strong></p>

                            @if($remainingRefundable > 0)
                                <form method="post" action="{{ route('tenant.commerce.refunds.store', $order) }}" class="form-stack">
                                    @csrf
                                    <label>Refund cents<input name="amount_cents" type="number" min="1" max="{{ $remainingRefundable }}" required></label>
                                    <label>Reason<input name="reason" maxlength="1000" placeholder="Optional internal reason"></label>
                                    <button class="button button-ghost">Request refund</button>
                                </form>
                            @else
                                <span class="pg-pill">Fully reserved / refunded</span>
                            @endif
                        </article>
                    @endforeach
                @endif
            </div>
        </section>

        <section class="pg-section">
            <div class="pg-grid pg-grid-2">
                <article class="pg-card">
                    <span class="pg-eyebrow">PAYOUT HISTORY</span>
                    <h2>Recent requests</h2>
                    @if($payouts->isEmpty())
                        <div class="pg-empty">No payouts requested.</div>
                    @else
                        @foreach($payouts as $payout)
                            <div class="row-between" style="margin-bottom:14px">
                                <div>
                                    <strong>${{ number_format($payout->amount_cents / 100, 2) }}</strong>
                                    <small>{{ ucfirst(str_replace('_', ' ', $payout->status)) }} · {{ \Carbon\Carbon::parse($payout->created_at)->format('M j, Y') }}</small>
                                    @if($payout->requested_by)
                                        <small>Requested by {{ $payout->requester_display_name ?: $payout->requester_name ?: '#'.$payout->requested_by }}</small>
                                    @endif
                                    @if($payout->status_reason)
                                        <small>{{ $payout->status_reason }}</small>
                                    @endif
                                </div>
                                <span class="pg-pill">{{ $payout->provider_reference ?: 'Awaiting provider' }}</span>
                            </div>
                        @endforeach
                    @endif
                </article>

                <article class="pg-card">
                    <span class="pg-eyebrow">REFUND HISTORY</span>
                    <h2>Recent refunds</h2>
                    @if($refunds->isEmpty())
                        <div class="pg-empty">No refunds recorded.</div>
                    @else
                        @foreach($refunds as $refund)
                            <div style="margin-bottom:16px">
                                <div class="row-between">
                                    <div>
                                        <strong>${{ number_format($refund->amount_cents / 100, 2) }} · Order #{{ $refund->order_id }}</strong>
                                        <small>{{ ucfirst(str_replace('_', ' ', $refund->status)) }}</small>
                                        @if($refund->requested_by)
                                            <small>Requested by {{ $refund->requester_display_name ?: $refund->requester_name ?: '#'.$refund->requested_by }}</small>
                                        @endif
                                    </div>
                                    @if($refund->status === 'manual_required')
                                        <span class="pg-pill pg-pill-warn">Reserved · manual review</span>
                                    @endif
                                </div>
                                <small>Platform fee reversal: ${{ number_format($refund->platform_fee_reversed_cents / 100, 2) }} · Club net reversal: ${{ number_format($refund->tenant_net_reversed_cents / 100, 2) }}</small>
                                @if($refund->reason)
                                    <small>Reason: {{ $refund->reason }}</small>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </article>
            </div>
        </section>
    </div>
</section>
@endsection
