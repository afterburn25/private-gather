<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommunityGroup extends Model
{
    protected $fillable = [
        'tenant_id', 'created_by', 'name', 'slug', 'description', 'visibility',
        'join_policy', 'status', 'allow_member_posts',
    ];

    protected function casts(): array
    {
        return ['allow_member_posts' => 'boolean'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

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

    public function activeMemberCount(): int
    {
        return $this->members()->wherePivot('status', 'active')->count();
    }
}
