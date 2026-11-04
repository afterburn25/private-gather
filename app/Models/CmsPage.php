<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'slug', 'title', 'seo_title', 'seo_description',
        'social_title', 'social_description', 'social_image_url',
        'status', 'is_homepage', 'published_at',
    ];

    protected function casts(): array
    {
        return ['is_homepage' => 'boolean', 'published_at' => 'datetime'];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function sections(): HasMany { return $this->hasMany(CmsSection::class)->orderBy('sort_order'); }
    public function formSubmissions(): HasMany { return $this->hasMany(CmsFormSubmission::class, 'cms_page_id'); }
}
