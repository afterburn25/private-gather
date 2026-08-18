<?php
namespace App\Http\Controllers\Member;
use App\Http\Controllers\Controller;
use App\Models\EventRsvp;
use Illuminate\Http\Request;
class DashboardController extends Controller {
 public function __invoke(Request $r){$rsvps=EventRsvp::with('event.tenant')->where('user_id',$r->user()->id)->latest()->limit(12)->get();return view('member.dashboard',['user'=>$r->user()->load(['profile','tenants']),'rsvps'=>$rsvps]);}
}
