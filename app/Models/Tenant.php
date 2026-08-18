<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsToMany;use Illuminate\Database\Eloquent\Relations\HasMany;use Illuminate\Database\Eloquent\Relations\HasOne;
class Tenant extends Model{
 use HasFactory;
 public const TYPE_CLUB='club';public const TYPE_ORGANIZER='organizer';public const TYPE_PRIVATE_HOST='private_host';
 protected $fillable=['name','slug','type','status','plan','settings'];
 protected function casts():array{return ['settings'=>'array'];}
 public function domains():HasMany{return $this->hasMany(TenantDomain::class);}
 public function primaryDomain():HasOne{return $this->hasOne(TenantDomain::class)->where('is_primary',true);}
 public function users():BelongsToMany{return $this->belongsToMany(User::class,'tenant_users')->withPivot(['role','status'])->withTimestamps();}
 public function events():HasMany{return $this->hasMany(Event::class);}
 public function pages():HasMany{return $this->hasMany(CmsPage::class);}
 public function settingsRecords():HasMany{return $this->hasMany(SiteSetting::class);}
 public function branding():HasOne{return $this->hasOne(TenantBranding::class);}
 public function subscription():HasOne{return $this->hasOne(TenantSubscription::class);}
 public function isActive():bool{return $this->status==='active';}
}