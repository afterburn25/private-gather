<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NetworkCommunityPost extends Model
{
    protected $fillable = ['network_community_group_id', 'event_id', 'user_id', 'body', 'status', 'is_pinned'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    public function group(): BelongsTo { return $this->belongsTo(NetworkCommunityGroup::class, 'network_community_group_id'); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function comments(): HasMany { return $this->hasMany(NetworkCommunityComment::class, 'post_id'); }
    public function reactions(): HasMany { return $this->hasMany(NetworkCommunityReaction::class, 'post_id'); }
}
