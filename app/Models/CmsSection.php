<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSection extends Model
{
    use HasFactory;

    protected $fillable = ['cms_page_id', 'type', 'name', 'content', 'settings', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return ['content' => 'array', 'settings' => 'array', 'sort_order' => 'integer', 'is_enabled' => 'boolean'];
    }

    public function page(): BelongsTo { return $this->belongsTo(CmsPage::class, 'cms_page_id'); }
}
