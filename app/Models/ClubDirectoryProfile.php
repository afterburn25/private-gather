<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClubDirectoryProfile extends Model
{
    protected $fillable = [
        'tenant_id', 'is_listed', 'listing_name', 'club_type', 'short_description',
        'city', 'region', 'country_code', 'postal_code', 'latitude', 'longitude',
        'amenities', 'contact_url', 'verified_at', 'featured_until',
    ];

    protected function casts(): array
    {
        return [
            'is_listed' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'amenities' => 'array',
            'verified_at' => 'datetime',
            'featured_until' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function displayName(): string
    {
        return trim((string) $this->listing_name) !== '' ? (string) $this->listing_name : (string) $this->tenant?->name;
    }
}
