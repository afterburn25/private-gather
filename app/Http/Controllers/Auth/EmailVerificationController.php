<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;use Illuminate\Foundation\Auth\EmailVerificationRequest;use Illuminate\Http\Request;
class EmailVerificationController extends Controller{
 public function notice(Request $r){return $r->user()->hasVerifiedEmail()?redirect()->route('dashboard'):view('auth.verify-email');}
 public function verify(EmailVerificationRequest $r){$r->fulfill();return redirect()->route('dashboard')->with('status','Email address verified.');}
 public function send(Request $r){if(!$r->user()->hasVerifiedEmail())$r->user()->sendEmailVerificationNotification();return back()->with('status','Verification link sent.');}
}