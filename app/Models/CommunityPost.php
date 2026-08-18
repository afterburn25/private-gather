<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommunityPost extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'body', 'status', 'is_pinned', 'edited_at'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'edited_at' => 'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function comments(): HasMany { return $this->hasMany(CommunityComment::class, 'post_id'); }
    public function reactions(): HasMany { return $this->hasMany(CommunityReaction::class, 'post_id'); }
}
