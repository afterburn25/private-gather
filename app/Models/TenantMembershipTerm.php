<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembershipTerm extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_GRACE = 'grace';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'user_id', 'membership_level_id', 'status', 'starts_at',
        'current_period_starts_at', 'current_period_ends_at', 'renews_at', 'expires_at',
        'auto_renew', 'cancel_at_period_end', 'source', 'provider', 'provider_reference',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function level(): BelongsTo { return $this->belongsTo(TenantMembershipLevel::class, 'membership_level_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isCurrentlyActive(): bool
    {
        if (! in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_GRACE], true)) return false;
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
