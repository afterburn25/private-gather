<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;use App\Models\DataRequest;use App\Models\SecurityEvent;use App\Services\TotpService;use Illuminate\Http\Request;use Illuminate\Support\Facades\Crypt;
class SecurityController extends Controller{
 public function index(Request $r){return view('member.security',['user'=>$r->user()]);}
 public function beginTwoFactor(Request $r,TotpService $totp){$secret=$totp->secret();$r->session()->put('2fa_setup_secret',$secret);return view('member.two-factor',['secret'=>$secret,'uri'=>$totp->uri($secret,$r->user()->email,config('app.name'))]);}
 public function confirmTwoFactor(Request $r,TotpService $totp){$d=$r->validate(['code'=>'required|string']);$secret=(string)$r->session()->get('2fa_setup_secret');abort_unless($secret&&$totp->verify($secret,$d['code']),422,'Invalid authenticator code.');$codes=$totp->recoveryCodes();$r->user()->forceFill(['two_factor_secret'=>Crypt::encryptString($secret),'two_factor_recovery_codes'=>Crypt::encryptString(json_encode($codes)),'two_factor_confirmed_at'=>now()])->save();$r->session()->forget('2fa_setup_secret');return view('member.recovery-codes',['codes'=>$codes]);}
 public function disableTwoFactor(Request $r){$r->validate(['password'=>'required|current_password']);$r->user()->forceFill(['two_factor_secret'=>null,'two_factor_recovery_codes'=>null,'two_factor_confirmed_at'=>null])->save();return back()->with('status','Two-factor authentication disabled.');}
 public function dataRequest(Request $r){$d=$r->validate(['type'=>'required|in:export,delete']);DataRequest::create(['user_id'=>$r->user()->id,'type'=>$d['type'],'status'=>'pending','requested_at'=>now()]);return back()->with('status','Privacy request submitted.');}
}