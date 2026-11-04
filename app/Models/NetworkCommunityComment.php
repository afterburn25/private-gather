<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NetworkCommunityComment extends Model
{
    protected $fillable = ['post_id', 'user_id', 'body', 'status'];

    public function post(): BelongsTo { return $this->belongsTo(NetworkCommunityPost::class, 'post_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
