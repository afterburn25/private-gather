<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(Request $request, BadgeService $badges): View
    {
        $assignments=$badges->activeForUser($request->user());
        return view('member.badges',[
            'globalBadges'=>$assignments->filter(fn($assignment)=>$assignment->badge->isGlobal())->values(),
            'tenantBadges'=>$assignments->reject(fn($assignment)=>$assignment->badge->isGlobal())->groupBy('tenant_id'),
        ]);
    }
}
