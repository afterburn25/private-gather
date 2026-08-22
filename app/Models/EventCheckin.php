<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCheckin extends Model
{
    protected $fillable = ['event_id', 'user_id', 'ticket_id', 'checked_in_by', 'method', 'guest_count', 'checked_in_at', 'metadata'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'metadata' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
