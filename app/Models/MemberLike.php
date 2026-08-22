<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberLike extends Model
{
    protected $fillable = [
        'tenant_id', 'liker_user_id', 'liked_user_id', 'status', 'source', 'matched_at',
    ];

    protected function casts(): array
    {
        return ['matched_at' => 'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function liker(): BelongsTo { return $this->belongsTo(User::class, 'liker_user_id'); }
    public function liked(): BelongsTo { return $this->belongsTo(User::class, 'liked_user_id'); }
}
