<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PrivateAlbumPhoto extends Model
{
    protected $fillable = ['album_id','path','media_type','mime_type','size_bytes','duration_seconds','thumbnail_path','caption','sort_order'];
    protected function casts(): array { return ['sort_order'=>'integer','size_bytes'=>'integer','duration_seconds'=>'integer']; }
    public function album(): BelongsTo { return $this->belongsTo(PrivateAlbum::class, 'album_id'); }
}
