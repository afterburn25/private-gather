<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CmsReusableBlock extends Model
{
    protected $fillable = ['tenant_id', 'created_by', 'name', 'type', 'content', 'settings', 'status'];

    protected function casts(): array
    {
        return ['content' => 'array', 'settings' => 'array'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
