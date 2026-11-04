<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NetworkCommunityGroup extends Model
{
    protected $fillable = ['created_by', 'name', 'slug', 'description', 'join_policy', 'status'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function posts(): HasMany { return $this->hasMany(NetworkCommunityPost::class); }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'network_community_group_members',
            'network_community_group_id',
            'user_id'
        )->withPivot(['role', 'status'])->withTimestamps();
    }
}
