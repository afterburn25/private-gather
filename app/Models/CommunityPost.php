<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommunityPost extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'wall_user_id',
        'group_id',
        'event_id',
        'post_type',
        'visibility',
        'share_to_club_wall',
        'body',
        'media_path',
        'media_type',
        'media_mime',
        'media_size',
        'poll_options',
        'status',
        'is_pinned',
        'edited_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'share_to_club_wall' => 'boolean',
            'poll_options' => 'array',
            'is_pinned' => 'boolean',
            'edited_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function wallUser(): BelongsTo { return $this->belongsTo(User::class, 'wall_user_id'); }
    public function group(): BelongsTo { return $this->belongsTo(CommunityGroup::class, 'group_id'); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function comments(): HasMany { return $this->hasMany(CommunityComment::class, 'post_id'); }
    public function reactions(): HasMany { return $this->hasMany(CommunityReaction::class, 'post_id'); }
    public function pollVotes(): HasMany { return $this->hasMany(CommunityPollVote::class, 'post_id'); }
}
