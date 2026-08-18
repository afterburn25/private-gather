<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model{
 protected $fillable=['reporter_id','tenant_id','reportable_type','reportable_id','category','details','status','assigned_to'];
 public function reporter(){return $this->belongsTo(User::class,'reporter_id');}
}