<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model {
    public $timestamps = false;
    protected $fillable=['tenant_id','user_id','action','subject_type','subject_id','before','after','ip_address','user_agent','created_at'];
    protected function casts(): array { return ['before'=>'array','after'=>'array','created_at'=>'datetime']; }
}
