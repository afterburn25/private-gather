<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantMembershipInvitation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id', 'membership_level_id', 'invited_by', 'email', 'token_digest',
        'status', 'message', 'expires_at', 'accepted_by', 'accepted_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function level(): BelongsTo { return $this->belongsTo(TenantMembershipLevel::class, 'membership_level_id'); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
    public function acceptedBy(): BelongsTo { return $this->belongsTo(User::class, 'accepted_by'); }

    public function isAcceptable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture() && $this->revoked_at === null;
    }
}
