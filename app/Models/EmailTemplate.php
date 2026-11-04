<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmailTemplate extends Model{protected $fillable=['tenant_id','key','name','subject','body_html','body_text','enabled'];protected function casts():array{return ['enabled'=>'boolean'];}}