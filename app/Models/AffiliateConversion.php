<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AffiliateConversion extends Model
{
    protected $fillable = ['affiliate_offer_id', 'external_reference', 'sale_amount_cents', 'commission_cents', 'currency', 'status', 'occurred_at', 'metadata'];

    protected function casts(): array
    {
        return ['sale_amount_cents' => 'integer', 'commission_cents' => 'integer', 'occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'affiliate_offer_id');
    }
}
