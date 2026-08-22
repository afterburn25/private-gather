<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MembershipLevel extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'code', 'description', 'price_cents', 'currency',
        'billing_interval', 'application_required', 'ticket_discount_percent',
        'active', 'sort_order', 'benefits',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'application_required' => 'boolean',
            'ticket_discount_percent' => 'integer',
            'active' => 'boolean',
            'sort_order' => 'integer',
            'benefits' => 'array',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
