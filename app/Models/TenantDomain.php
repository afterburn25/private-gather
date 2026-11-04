<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TenantDomain extends Model{
 use HasFactory;
 public const TYPE_PLATFORM_SUBDOMAIN='platform_subdomain';public const TYPE_CUSTOM_DOMAIN='custom_domain';public const TYPE_CUSTOM_SUBDOMAIN='custom_subdomain';
 public const STATUS_PENDING='pending';public const STATUS_VERIFYING='verifying';public const STATUS_ACTIVE='active';public const STATUS_FAILED='failed';
 protected $fillable=['tenant_id','domain','type','is_primary','status','verification_token','verified_at','ssl_status','ssl_last_checked_at','redirect_to_primary','dns_status','dns_last_checked_at','last_error','health'];
 protected function casts():array{return ['is_primary'=>'boolean','verified_at'=>'datetime','ssl_last_checked_at'=>'datetime','redirect_to_primary'=>'boolean','dns_last_checked_at'=>'datetime','health'=>'array'];}
 public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
}