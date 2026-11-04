<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_TENANT = 'tenant';

    public const ISSUE_MANUAL = 'manual';
    public const ISSUE_AUTOMATIC = 'automatic';
    public const ISSUE_VERIFICATION = 'verification';
    public const ISSUE_MEMBERSHIP = 'membership';

    protected $fillable = [
        'tenant_id', 'scope', 'name', 'slug', 'description', 'icon', 'badge_color',
        'text_color', 'category', 'visibility', 'issuance_type', 'criteria',
        'expires_after_days', 'is_active', 'is_system_reserved', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'expires_after_days' => 'integer',
            'is_active' => 'boolean',
            'is_system_reserved' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function isGlobal(): bool
    {
        return $this->scope === self::SCOPE_GLOBAL && $this->tenant_id === null;
    }
}
