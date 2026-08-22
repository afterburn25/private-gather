<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunityGroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'role', 'status'];
    public function group(): BelongsTo { return $this->belongsTo(CommunityGroup::class, 'group_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
