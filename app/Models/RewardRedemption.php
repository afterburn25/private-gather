<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class RewardRedemption extends Model{protected $fillable=['tenant_id','reward_id','user_id','points_spent','status','fulfilled_by','fulfilled_at'];protected function casts():array{return ['points_spent'=>'integer','fulfilled_at'=>'datetime'];}public function reward():BelongsTo{return $this->belongsTo(ClubReward::class,'reward_id');}public function user():BelongsTo{return $this->belongsTo(User::class);}}
