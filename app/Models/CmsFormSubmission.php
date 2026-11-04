<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CmsFormSubmission extends Model
{
    protected $fillable = [
        'tenant_id', 'cms_page_id', 'cms_section_id', 'user_id',
        'name', 'email', 'message', 'privacy_ack', 'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['privacy_ack' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function page(): BelongsTo { return $this->belongsTo(CmsPage::class, 'cms_page_id'); }
    public function section(): BelongsTo { return $this->belongsTo(CmsSection::class, 'cms_section_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
