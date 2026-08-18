<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class CmsNavigationItem extends Model{protected $fillable=['tenant_id','location','label','url','sort_order','is_enabled'];protected function casts():array{return['is_enabled'=>'boolean'];}}
