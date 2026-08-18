<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;use App\Models\User;use Illuminate\Auth\Events\PasswordReset;use Illuminate\Http\Request;use Illuminate\Support\Facades\Password;use Illuminate\Support\Str;use Illuminate\Validation\Rules\Password as PasswordRule;
class PasswordResetController extends Controller{
 public function requestForm(){return view('auth.forgot-password');}
 public function send(Request $r){$r->validate(['email'=>'required|email']);$status=Password::sendResetLink($r->only('email'));return $status===Password::RESET_LINK_SENT?back()->with('status',__($status)):back()->withErrors(['email'=>__($status)]);}
 public function resetForm(Request $r,string $token){return view('auth.reset-password',['token'=>$token,'email'=>$r->query('email')]);}
 public function reset(Request $r){$d=$r->validate(['token'=>'required','email'=>'required|email','password'=>['required','confirmed',PasswordRule::min(10)->letters()->numbers()]]);$status=Password::reset($d,function(User $user,string $password){$user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();event(new PasswordReset($user));});return $status===Password::PASSWORD_RESET?redirect()->route('login')->with('status',__($status)):back()->withErrors(['email'=>__($status)]);}
}