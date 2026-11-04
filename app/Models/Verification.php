<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verification extends Model
{
    protected $fillable=['user_id','tenant_id','type','status','provider','provider_reference','reviewed_by','verified_at','expires_at','metadata'];
    protected function casts():array{return ['verified_at'=>'datetime','expires_at'=>'datetime','metadata'=>'array'];}
    public function user():BelongsTo{return $this->belongsTo(User::class);}
    public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}
    public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by');}
}
