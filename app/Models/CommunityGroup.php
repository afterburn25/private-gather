<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommunityGroup extends Model
{
    public const TYPE_COMMUNITY = 'community';
    public const TYPE_CHAPTER = 'chapter';

    protected $fillable = [
        'tenant_id', 'created_by', 'name', 'slug', 'description', 'group_type',
        'city', 'region', 'operator_managed', 'chapter_lead_user_id', 'branding', 'visibility',
        'join_policy', 'status', 'allow_member_posts',
    ];

    protected function casts(): array
    {
        return [
            'allow_member_posts' => 'boolean',
            'operator_managed' => 'boolean',
            'branding' => 'array',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function chapterLead(): BelongsTo { return $this->belongsTo(User::class, 'chapter_lead_user_id'); }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_group_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'community_group_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'community_group_id');
    }

    public function activeMemberCount(): int
    {
        return $this->members()->wherePivot('status', 'active')->count();
    }

    public function isChapter(): bool
    {
        return $this->group_type === self::TYPE_CHAPTER;
    }

    public function locationLabel(): ?string
    {
        $parts = array_values(array_filter([$this->city, $this->region], fn ($value) => filled($value)));
        return $parts === [] ? null : implode(', ', $parts);
    }
}
