<?php
namespace App\Http\Controllers;
use App\Models\Report;use Illuminate\Http\Request;
class ReportController extends Controller{
 public function store(Request $r){$d=$r->validate(['reportable_type'=>'required|in:user,event,tenant,message','reportable_id'=>'required|integer|min:1','category'=>'required|in:safety,harassment,spam,fraud,privacy,inappropriate,other','details'=>'nullable|string|max:5000']);Report::create(['reporter_id'=>$r->user()->id,'tenant_id'=>app(\App\Tenancy\TenantContext::class)->tenant()?->id,...$d]);return back()->with('status','Report submitted for review.');}
}