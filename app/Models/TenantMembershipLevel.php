<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantMembershipLevel extends Model
{
    public const INTERVAL_NONE = 'none';
    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_QUARTERLY = 'quarterly';
    public const INTERVAL_ANNUAL = 'annual';
    public const INTERVAL_LIFETIME = 'lifetime';
    public const INTERVAL_CUSTOM = 'custom';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'price_cents', 'currency',
        'billing_interval', 'duration_days', 'profile_eligibility', 'guest_limit',
        'event_discount_percent', 'requires_approval', 'is_default', 'is_active',
        'sort_order', 'benefits',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'duration_days' => 'integer',
            'guest_limit' => 'integer',
            'event_discount_percent' => 'integer',
            'requires_approval' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'benefits' => 'array',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function terms(): HasMany { return $this->hasMany(TenantMembershipTerm::class, 'membership_level_id'); }

    public function priceLabel(): string
    {
        if ($this->price_cents === 0) return 'Free';
        return '$'.number_format($this->price_cents / 100, 2);
    }
}
