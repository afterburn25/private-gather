<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConsentRecord extends Model{protected $fillable=['user_id','consent_type','document_version','granted','ip_hash','recorded_at'];protected function casts():array{return ['granted'=>'boolean','recorded_at'=>'datetime'];}}