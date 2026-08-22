<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformNotification extends Model
{
    use HasUuids;

    protected $table = 'platform_notifications';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'user_id', 'tenant_id', 'type', 'data', 'read_at'];
    protected function casts(): array { return ['data' => 'array', 'read_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
