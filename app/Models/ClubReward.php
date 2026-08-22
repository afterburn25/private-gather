<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class ClubReward extends Model{protected $fillable=['tenant_id','name','description','points_cost','inventory','active'];protected function casts():array{return ['points_cost'=>'integer','inventory'=>'integer','active'=>'boolean'];}public function tenant():BelongsTo{return $this->belongsTo(Tenant::class);}}
