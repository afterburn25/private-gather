<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Verification extends Model{
 protected $fillable=['user_id','tenant_id','type','status','provider','provider_reference','provider_reference_hash','reviewed_by','verified_at','expires_at','metadata'];
 protected function casts():array{return ['verified_at'=>'datetime','expires_at'=>'datetime','metadata'=>'array'];}
}