<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformUpgrade extends Model
{
    protected $fillable = [
        'upgrade_id',
        'from_version',
        'to_version',
        'status',
        'package_name',
        'package_sha256',
        'backup_path',
        'log_path',
        'initiated_by',
        'started_at',
        'completed_at',
        'error_message',
        'manifest',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
