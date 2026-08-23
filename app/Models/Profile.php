<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'partner_user_id',
        'profile_type',
        'lifestyle_identity',
        'relationship_status',
        'experience_level',
        'pronouns',
        'headline',
        'bio',
        'city',
        'region',
        'avatar_path',
        'cover_path',
        'interests',
        'looking_for',
        'lifestyle_interests',
        'boundaries',
        'visibility',
        'discoverable',
        'message_permissions',
        'show_age',
        'show_last_active',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'looking_for' => 'array',
            'lifestyle_interests' => 'array',
            'visibility' => 'array',
            'discoverable' => 'boolean',
            'show_age' => 'boolean',
            'show_last_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProfilePhoto::class)->orderBy('sort_order');
    }
}
