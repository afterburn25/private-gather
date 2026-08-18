<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Plan;use Illuminate\Http\Request;
class PlanController extends Controller{
 public function index(){return view('admin.plans.index',['plans'=>Plan::orderBy('sort_order')->get()]);}
 public function store(Request $r){$d=$r->validate(['code'=>'required|alpha_dash|max:80|unique:plans,code','name'=>'required|string|max:120','price_monthly_cents'=>'required|integer|min:0','features_json'=>'nullable|json']);Plan::create(['code'=>$d['code'],'name'=>$d['name'],'price_monthly_cents'=>$d['price_monthly_cents'],'features'=>json_decode($d['features_json']??'{}',true),'currency'=>'USD','active'=>true]);return back()->with('status','Plan created.');}
 public function update(Request $r,Plan $plan){$d=$r->validate(['name'=>'required|string|max:120','price_monthly_cents'=>'required|integer|min:0','features_json'=>'required|json','active'=>'nullable|boolean']);$plan->update(['name'=>$d['name'],'price_monthly_cents'=>$d['price_monthly_cents'],'features'=>json_decode($d['features_json'],true),'active'=>$r->boolean('active')]);return back()->with('status','Plan updated.');}
}