<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\PlatformNotification;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificationController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->tenant();
        $notifications = PlatformNotification::query()->where('user_id', $request->user()->id)
            ->when($tenant, fn ($q) => $q->where(fn ($x) => $x->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id)))
            ->latest()->paginate(50);
        $preferences = NotificationPreference::firstOrCreate(['user_id' => $request->user()->id]);
        return view('member.notifications', compact('notifications','preferences','tenant'));
    }

    public function read(Request $request, PlatformNotification $notification): RedirectResponse
    {
        abort_unless((int)$notification->user_id === (int)$request->user()->id, 404);
        $notification->update(['read_at'=>now()]);
        return back();
    }

    public function readAll(Request $request, TenantContext $context): RedirectResponse
    {
        PlatformNotification::query()->where('user_id',$request->user()->id)->whereNull('read_at')
            ->when($context->check(), fn($q)=>$q->where(fn($x)=>$x->whereNull('tenant_id')->orWhere('tenant_id',$context->id())))
            ->update(['read_at'=>now()]);
        return back()->with('status','Notifications marked read.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $request->validate([
            'email_events'=>'nullable|boolean','email_messages'=>'nullable|boolean','email_reactions'=>'nullable|boolean','email_connections'=>'nullable|boolean',
            'email_group_activity'=>'nullable|boolean','email_marketing'=>'nullable|boolean','browser_notifications'=>'nullable|boolean','in_app_messages'=>'nullable|boolean',
            'in_app_reactions'=>'nullable|boolean','in_app_connections'=>'nullable|boolean','in_app_events'=>'nullable|boolean',
        ]);
        $pref=NotificationPreference::firstOrCreate(['user_id'=>$request->user()->id]);
        foreach(['email_events','email_messages','email_reactions','email_connections','email_group_activity','email_marketing','browser_notifications','in_app_messages','in_app_reactions','in_app_connections','in_app_events'] as $field){
            $pref->{$field}=$request->boolean($field);
        }
        $pref->save();
        return back()->with('status','Notification preferences saved.');
    }
}
