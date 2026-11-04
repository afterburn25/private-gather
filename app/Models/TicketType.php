<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    public const ELIGIBILITY_ANY = 'any';
    public const ELIGIBILITY_COUPLE = 'couple';
    public const ELIGIBILITY_INDIVIDUAL = 'individual';

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'profile_eligibility',
        'membership_required',
        'approval_required',
        'price_cents',
        'currency',
        'quantity',
        'max_per_order',
        'sales_start_at',
        'sales_end_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'membership_required' => 'boolean',
            'approval_required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function eligibilityLabel(): string
    {
        return match ($this->profile_eligibility) {
            self::ELIGIBILITY_COUPLE => 'Couple profiles',
            self::ELIGIBILITY_INDIVIDUAL => 'Individual profiles',
            default => 'All eligible profiles',
        };
    }
}
