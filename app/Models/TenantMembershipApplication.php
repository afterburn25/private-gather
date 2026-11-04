<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembershipApplication extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'profile_type',
        'status',
        'referred_by',
        'introduction',
        'answers',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
