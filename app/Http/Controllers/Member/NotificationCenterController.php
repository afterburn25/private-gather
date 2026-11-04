<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class NotificationCenterController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = (int) $request->user()->id;

        return view('member.notifications', [
            'notifications' => DB::table('platform_notifications')
                ->where('user_id', $userId)
                ->latest()
                ->paginate(40),
            'prefs' => DB::table('notification_preferences')->where('user_id', $userId)->first(),
            'pushCount' => DB::table('push_subscriptions')->where('user_id', $userId)->count(),
            'pushPublicKey' => (string) config('services.webpush.public_key'),
        ]);
    }
}
