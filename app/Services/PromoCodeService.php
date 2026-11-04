<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PromoCodeService
{
    public function quote(Event $event, ?string $rawCode, int $subtotalCents, bool $lock = false): array
    {
        $code = strtoupper(trim((string) $rawCode));
        if ($code === '') {
            return ['code' => null, 'promo_id' => null, 'discount_cents' => 0, 'total_cents' => $subtotalCents];
        }

        $query = DB::table('promo_codes')
            ->where('tenant_id', $event->tenant_id)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('active', true)
            ->where(function ($builder) use ($event): void {
                $builder->whereNull('event_id')->orWhere('event_id', $event->id);
            });
        if ($lock) $query->lockForUpdate();
        $promo = $query->first();

        if (! $promo || ($promo->expires_at && now()->gte($promo->expires_at))) {
            throw ValidationException::withMessages(['promo_code' => 'That promo or comp code is invalid or expired.']);
        }
        if ($promo->max_uses !== null && (int) $promo->uses >= (int) $promo->max_uses) {
            throw ValidationException::withMessages(['promo_code' => 'That promo or comp code has reached its redemption limit.']);
        }

        $discount = match ((string) $promo->discount_type) {
            'percent' => (int) floor($subtotalCents * min(100, (int) $promo->discount_value) / 100),
            'fixed' => min($subtotalCents, (int) $promo->discount_value),
            default => throw ValidationException::withMessages(['promo_code' => 'That promo code has an unsupported discount type.']),
        };
        $discount = max(0, min($subtotalCents, $discount));

        return [
            'code' => (string) $promo->code,
            'promo_id' => (int) $promo->id,
            'discount_cents' => $discount,
            'total_cents' => max(0, $subtotalCents - $discount),
        ];
    }

    public function consume(int $promoId): void
    {
        $promo = DB::table('promo_codes')->where('id', $promoId)->lockForUpdate()->first();
        abort_unless($promo && $promo->active, 422, 'Promo or comp code is no longer active.');
        if ($promo->expires_at) abort_if(now()->gte($promo->expires_at), 422, 'Promo or comp code expired before payment completed.');
        if ($promo->max_uses !== null) abort_if((int) $promo->uses >= (int) $promo->max_uses, 422, 'Promo or comp code reached its redemption limit before payment completed.');
        DB::table('promo_codes')->where('id', $promoId)->update(['uses' => DB::raw('uses + 1'), 'updated_at' => now()]);
    }

    public function revalidateOrderPromo(object $order, Event $event, bool $lock = true): ?int
    {
        if (! filled($order->promo_code)) return null;

        try {
            $quote = $this->quote($event, (string) $order->promo_code, (int) $order->subtotal_cents, $lock);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            abort(422, is_string($message) && $message !== '' ? $message : 'The promo or comp code is no longer valid for payment completion.');
        }

        if ((int) $quote['discount_cents'] !== (int) $order->discount_cents || (int) $quote['total_cents'] !== (int) $order->total_cents) {
            abort(422, 'The promo calculation changed before payment completed. Review the order instead of silently changing its total.');
        }
        return (int) $quote['promo_id'];
    }
}
