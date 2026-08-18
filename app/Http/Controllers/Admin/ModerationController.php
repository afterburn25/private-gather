<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\Report;use Illuminate\Http\Request;
class ModerationController extends Controller{
 public function index(){return view('admin.moderation.index',['reports'=>Report::with('reporter')->latest()->paginate(100)]);}
 public function update(Request $r,Report $report){$d=$r->validate(['status'=>'required|in:open,reviewing,resolved,dismissed']);$report->update(['status'=>$d['status'],'assigned_to'=>$r->user()->id]);return back()->with('status','Moderation case updated.');}
}