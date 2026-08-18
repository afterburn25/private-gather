<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Profile extends Model {
 protected $fillable=['user_id','partner_user_id','profile_type','headline','bio','city','region','avatar_path','cover_path','interests','visibility','discoverable'];
 protected function casts(): array {return ['interests'=>'array','visibility'=>'array','discoverable'=>'boolean'];}
 public function user(): BelongsTo{return $this->belongsTo(User::class);}
 public function partner(): BelongsTo{return $this->belongsTo(User::class,'partner_user_id');}
 public function photos(): HasMany{return $this->hasMany(ProfilePhoto::class)->orderBy('sort_order');}
}
