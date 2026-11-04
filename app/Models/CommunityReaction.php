<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunityReaction extends Model
{
    protected $fillable = ['tenant_id', 'post_id', 'user_id', 'reaction'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function post(): BelongsTo { return $this->belongsTo(CommunityPost::class, 'post_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
