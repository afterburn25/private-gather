<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AffiliateClick extends Model
{
    protected $fillable = ['affiliate_offer_id', 'user_id', 'tenant_id', 'placement', 'referrer_host', 'ip_hash', 'user_agent_hash', 'clicked_at'];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    public function offer(): BelongsTo { return $this->belongsTo(AffiliateOffer::class, 'affiliate_offer_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
