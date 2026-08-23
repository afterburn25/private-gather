<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ProductCompletionService
{
    public function notify(int $userId, string $type, array $data): void
    {
        DB::table('platform_notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $userId, 'type' => $type,
            'data' => json_encode($data, JSON_THROW_ON_ERROR), 'read_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function recommendations(?User $user, int $limit = 12): Collection
    {
        $events = Event::query()->with('tenant')->withCount('rsvps')
            ->where('status','published')->where('visibility','public')->where('starts_at','>=',now())
            ->whereHas('tenant', fn ($q) => $q->where('status','active')->where('settings->marketplace_enabled',true))
            ->orderBy('starts_at')->limit(80)->get();
        if (! $user) return $events->take($limit)->values();
        $profile = $user->profile;
        $interests = collect($profile?->interests ?? [])->map(fn ($v) => strtolower((string)$v));
        $followed = DB::table('user_follows')->where('user_id',$user->id)->where('target_type','tenant')->pluck('target_id')->map(fn ($v)=>(int)$v)->all();
        $favoriteEvents = DB::table('favorites')->where('user_id',$user->id)->where('target_type','event')->pluck('target_id')->map(fn ($v)=>(int)$v)->all();
        return $events->map(function (Event $event) use ($profile,$interests,$followed,$favoriteEvents) {
            $score = 0;
            if ($profile?->city && $event->city && strcasecmp($profile->city,$event->city)===0) $score += 5;
            if (in_array((int)$event->tenant_id,$followed,true)) $score += 6;
            if (in_array((int)$event->id,$favoriteEvents,true)) $score += 3;
            $haystack = strtolower($event->title.' '.$event->category.' '.$event->summary);
            $score += $interests->filter(fn ($term) => $term !== '' && str_contains($haystack,$term))->count() * 2;
            $score += min(4,(int) floor(((int)$event->rsvps_count)/10));
            $event->setAttribute('recommendation_score',$score);
            return $event;
        })->sortByDesc('recommendation_score')->take($limit)->values();
    }

    public function recordPaidOrder(Order $order): void
    {
        $order->loadMissing('tenant','payments');
        $feeBps = max(0,min(5000,(int) data_get($order->tenant?->settings,'platform_fee_bps',config('platform.marketplace_fee_bps',1000))));
        $gross = (int) $order->total_cents;
        $fee = (int) round($gross * ($feeBps / 10000));
        $payment = $order->payments->firstWhere('status','paid') ?? $order->payments->first();
        DB::table('marketplace_ledger_entries')->updateOrInsert(
            ['order_id'=>$order->id,'type'=>'ticket_sale'],
            ['tenant_id'=>$order->tenant_id,'payment_id'=>$payment?->id,'gross_cents'=>$gross,'platform_fee_cents'=>$fee,'tenant_net_cents'=>$gross-$fee,'currency'=>$order->currency,'status'=>'posted','provider_reference'=>$payment?->provider_reference,'metadata'=>json_encode(['fee_bps'=>$feeBps]),'created_at'=>now(),'updated_at'=>now()]
        );
    }

    public function availablePayoutCents(int $tenantId): int
    {
        $earned = (int) DB::table('marketplace_ledger_entries')->where('tenant_id',$tenantId)->where('status','posted')->sum('tenant_net_cents');
        $reserved = (int) DB::table('payouts')->where('tenant_id',$tenantId)->whereIn('status',['requested','processing','paid'])->sum('amount_cents');
        $refunds = (int) DB::table('refunds')->where('tenant_id',$tenantId)->whereIn('status',['processing','completed'])->sum('amount_cents');
        return max(0,$earned-$reserved-$refunds);
    }

    public function domainAvailability(string $domain): array
    {
        $domain = strtolower(trim($domain));
        if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',$domain)) return ['domain'=>$domain,'status'=>'invalid'];
        $endpoint = trim((string) config('services.domain_registrar.endpoint'));
        $token = trim((string) config('services.domain_registrar.token'));
        if ($endpoint === '' || $token === '') return ['domain'=>$domain,'status'=>'provider_required','message'=>'Connect a registrar provider for authoritative availability and pricing.'];
        $response = Http::timeout(10)->withToken($token)->acceptJson()->get(rtrim($endpoint,'/').'/availability',['domain'=>$domain]);
        if (! $response->successful()) return ['domain'=>$domain,'status'=>'provider_error','message'=>'Registrar availability service did not return a usable response.'];
        return ['domain'=>$domain,'status'=>$response->boolean('available')?'available':'unavailable','price_cents'=>$response->integer('price_cents') ?: null,'currency'=>$response->string('currency')->toString() ?: 'USD'];
    }

    public function requestDomainRegistration(Tenant $tenant, string $domain): array
    {
        $availability = $this->domainAvailability($domain);
        $status = $availability['status'] === 'available' ? 'pending_registration' : ($availability['status'] === 'provider_required' ? 'awaiting_provider' : 'blocked');
        DB::table('domain_orders')->updateOrInsert(['tenant_id'=>$tenant->id,'domain'=>strtolower($domain)],['provider'=>config('services.domain_registrar.name','manual'),'status'=>$status,'price_cents'=>$availability['price_cents'] ?? null,'currency'=>$availability['currency'] ?? 'USD','requested_at'=>now(),'metadata'=>json_encode($availability),'created_at'=>now(),'updated_at'=>now()]);
        return $availability + ['order_status'=>$status];
    }

    public function readiness(): array
    {
        $checks = [
            'storage_writable' => is_writable(storage_path()),
            'logs_writable' => is_writable(storage_path('logs')),
            'app_key' => (string) config('app.key') !== '',
            'database' => true,
            'queue_configured' => (string) config('queue.default') !== '',
            'mail_configured' => ! in_array((string) config('mail.default'),['log','array'],true),
            'object_storage' => in_array((string) config('filesystems.default'),['s3','gcs','r2'],true),
        ];
        try { DB::select('select 1'); } catch (\Throwable) { $checks['database'] = false; }
        return $checks;
    }
}
