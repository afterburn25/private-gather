<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DataRequest extends Model{protected $fillable=['user_id','type','status','requested_at','completed_at','artifact_path','admin_notes'];protected function casts():array{return ['requested_at'=>'datetime','completed_at'=>'datetime'];}}