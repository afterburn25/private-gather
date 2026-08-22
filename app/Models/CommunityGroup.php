<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommunityGroup extends Model
{
    protected $fillable = ['tenant_id','owner_user_id','name','slug','description','cover_path','group_type','visibility','allow_member_posts','is_featured','status'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    protected function casts(): array { return ['allow_member_posts'=>'boolean','is_featured'=>'boolean']; }
    public function members(): HasMany { return $this->hasMany(CommunityGroupMember::class, 'group_id'); }
    public function invites(): HasMany { return $this->hasMany(CommunityGroupInvite::class, 'group_id'); }
    public function posts(): HasMany { return $this->hasMany(CommunityPost::class, 'group_id'); }
}
