<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PrivateAlbum extends Model
{
    protected $fillable = ['tenant_id', 'owner_user_id', 'title', 'description', 'visibility', 'status'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function photos(): HasMany { return $this->hasMany(PrivateAlbumPhoto::class, 'album_id')->orderBy('sort_order'); }
    public function grants(): HasMany { return $this->hasMany(PrivateAlbumAccessGrant::class, 'album_id'); }
}
