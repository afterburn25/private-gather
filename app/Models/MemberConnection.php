<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberConnection extends Model
{
    protected $fillable = [
        'tenant_id', 'user_one_id', 'user_two_id', 'requested_by_user_id',
        'status', 'accepted_at',
    ];

    protected function casts(): array { return ['accepted_at' => 'datetime']; }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function userOne(): BelongsTo { return $this->belongsTo(User::class, 'user_one_id'); }
    public function userTwo(): BelongsTo { return $this->belongsTo(User::class, 'user_two_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }

    public static function orderedPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
