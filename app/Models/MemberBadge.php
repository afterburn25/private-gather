<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberBadge extends Model
{
    protected $fillable = ['tenant_id','user_id','code','label','icon','visibility','awarded_by','awarded_at','expires_at'];
    protected function casts(): array { return ['awarded_at'=>'datetime','expires_at'=>'datetime']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function awarder(): BelongsTo { return $this->belongsTo(User::class, 'awarded_by'); }
}
