<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunityComment extends Model
{
    protected $fillable = ['tenant_id', 'post_id', 'user_id', 'body', 'status', 'edited_at'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function post(): BelongsTo { return $this->belongsTo(CommunityPost::class, 'post_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
