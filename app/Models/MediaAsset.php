<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MediaAsset extends Model{
 protected $fillable=['tenant_id','uploaded_by','disk','path','original_name','mime_type','size','alt_text','visibility','sha256','metadata_stripped'];
 protected function casts():array{return ['metadata_stripped'=>'boolean'];}
 public function tenant(){return $this->belongsTo(Tenant::class);}
}