<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\User;use Illuminate\Http\Request;
class UserController extends Controller{
 public function index(Request $r){$q=User::query();if($s=trim((string)$r->query('q'))) $q->where(fn($x)=>$x->where('email','like','%'.$s.'%')->orWhere('display_name','like','%'.$s.'%')->orWhere('name','like','%'.$s.'%'));return view('admin.users.index',['users'=>$q->latest()->paginate(100)->withQueryString()]);}
 public function update(Request $r,User $user){$d=$r->validate(['status'=>'required|in:active,suspended,banned','is_platform_admin'=>'nullable|boolean']);abort_if($user->id===$r->user()->id&&$d['status']!=='active',422,'You cannot disable your own current administrator account.');$user->update(['status'=>$d['status'],'is_platform_admin'=>$r->boolean('is_platform_admin')]);return back()->with('status','User updated.');}
}