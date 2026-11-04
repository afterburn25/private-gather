<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TenantBranding extends Model{
 protected $table='tenant_branding';
 protected $fillable=['tenant_id','logo_path','favicon_path','primary_color','accent_color','font_family','email_from_name','show_platform_branding','theme'];
 protected function casts():array{return ['show_platform_branding'=>'boolean','theme'=>'array'];}
 public function tenant(){return $this->belongsTo(Tenant::class);}
}