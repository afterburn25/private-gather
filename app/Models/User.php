<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Relations\BelongsToMany;use Illuminate\Database\Eloquent\Relations\HasMany;use Illuminate\Database\Eloquent\Relations\HasOne;use Illuminate\Foundation\Auth\User as Authenticatable;use Illuminate\Notifications\Notifiable;
class User extends Authenticatable implements MustVerifyEmail{
 use HasFactory,Notifiable;
 protected $fillable=['name','display_name','email','password','date_of_birth','status','is_platform_admin','adult_confirmed_at','terms_accepted_at','last_login_at','locale','privacy_accepted_at','privacy_version'];
 protected $hidden=['password','remember_token','two_factor_secret','two_factor_recovery_codes'];
 protected function casts():array{return ['email_verified_at'=>'datetime','date_of_birth'=>'date','password'=>'hashed','is_platform_admin'=>'boolean','adult_confirmed_at'=>'datetime','terms_accepted_at'=>'datetime','last_login_at'=>'datetime','privacy_accepted_at'=>'datetime','two_factor_confirmed_at'=>'datetime'];}
 public function tenants():BelongsToMany{return $this->belongsToMany(Tenant::class,'tenant_users')->withPivot(['role','status'])->withTimestamps();}
 public function membershipApplications():HasMany{return $this->hasMany(TenantMembershipApplication::class);}
 public function profile():HasOne{return $this->hasOne(Profile::class);}
 public function tickets():HasMany{return $this->hasMany(Ticket::class);}
 public function isAdult():bool{return $this->date_of_birth?->lte(now()->subYears(18))??false;}
}
