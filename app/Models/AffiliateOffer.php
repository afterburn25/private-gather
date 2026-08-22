<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AffiliateOffer extends Model
{
    protected $fillable = [
        'slug', 'title', 'advertiser_name', 'relationship_type', 'category', 'description',
        'affiliate_url', 'image_url', 'cta_label', 'status', 'placements', 'country_code',
        'region', 'city', 'priority', 'starts_at', 'ends_at', 'disclosure', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }
}
