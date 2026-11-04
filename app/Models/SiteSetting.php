<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'group', 'key', 'value', 'type', 'is_public'];
    protected function casts(): array { return ['is_public' => 'boolean']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
