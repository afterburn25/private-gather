<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id','email_events','email_messages','email_reactions','email_connections','email_group_activity','email_marketing',
        'browser_notifications','in_app_messages','in_app_reactions','in_app_connections','in_app_events',
    ];
    protected function casts(): array
    {
        return [
            'email_events'=>'boolean','email_messages'=>'boolean','email_reactions'=>'boolean','email_connections'=>'boolean',
            'email_group_activity'=>'boolean','email_marketing'=>'boolean','browser_notifications'=>'boolean','in_app_messages'=>'boolean',
            'in_app_reactions'=>'boolean','in_app_connections'=>'boolean','in_app_events'=>'boolean',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
