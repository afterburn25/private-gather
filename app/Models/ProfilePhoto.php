<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ProfilePhoto extends Model {
 protected $fillable=['profile_id','path','visibility','sort_order','is_primary'];
 protected function casts():array{return['is_primary'=>'boolean'];}
 public function profile():BelongsTo{return $this->belongsTo(Profile::class);}
}
