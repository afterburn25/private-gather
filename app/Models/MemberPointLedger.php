<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class MemberPointLedger extends Model{protected $table='member_point_ledger';protected $fillable=['tenant_id','user_id','points','reason','source_type','source_id','issued_by'];protected function casts():array{return ['points'=>'integer'];}public function user():BelongsTo{return $this->belongsTo(User::class);}public function issuer():BelongsTo{return $this->belongsTo(User::class,'issued_by');}}
