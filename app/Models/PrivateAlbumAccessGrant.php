<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PrivateAlbumAccessGrant extends Model
{
    protected $fillable = ['album_id', 'user_id', 'granted_by_user_id', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function album(): BelongsTo { return $this->belongsTo(PrivateAlbum::class, 'album_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function grantedBy(): BelongsTo { return $this->belongsTo(User::class, 'granted_by_user_id'); }
}
