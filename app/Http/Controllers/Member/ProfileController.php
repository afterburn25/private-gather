<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;
class ProfileController extends Controller {
 public function edit(Request $r){return view('member.profile',['profile'=>$r->user()->profile]);}
 public function update(Request $r){$d=$r->validate(['display_name'=>'required|string|max:80','profile_type'=>'required|in:individual,couple','headline'=>'nullable|string|max:160','bio'=>'nullable|string|max:5000','city'=>'nullable|string|max:120','region'=>'nullable|string|max:120','interests'=>'nullable|string|max:1000']);$r->user()->update(['display_name'=>$d['display_name']]);$p=$r->user()->profile()->firstOrCreate([],['profile_type'=>'individual']);$before=$p->toArray();$p->update(['profile_type'=>$d['profile_type'],'headline'=>$d['headline']??null,'bio'=>$d['bio']??null,'city'=>$d['city']??null,'region'=>$d['region']??null,'interests'=>array_values(array_filter(array_map('trim',explode(',',(string)($d['interests']??''))))),'discoverable'=>$r->boolean('discoverable')]);Audit::write('profile.updated',$p,$before,$p->fresh()->toArray(),request:$r);return back()->with('status','Profile updated.');}
}
