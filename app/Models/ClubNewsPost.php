<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClubNewsPost extends Model
{
    protected $fillable = ['tenant_id','user_id','title','slug','excerpt','body','cover_path','status','visibility','is_pinned','published_at'];
    protected function casts(): array { return ['is_pinned'=>'boolean','published_at'=>'datetime']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
