<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(): View
    {
        $badges = Badge::query()->whereNull('tenant_id')->where('scope', Badge::SCOPE_GLOBAL)
            ->withCount(['assignments as active_assignments_count' => fn ($query) => $query->whereNull('revoked_at')->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()))])
            ->orderBy('category')->orderBy('name')->get();
        $assignments = UserBadge::query()->with(['badge', 'user', 'issuer'])->whereNull('tenant_id')->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('issued_at')->limit(100)->get();
        return view('admin.badges.index', compact('badges', 'assignments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'], 'slug' => ['nullable','string','max:120'], 'description' => ['nullable','string','max:2000'],
            'icon' => ['nullable','string','max:64'], 'badge_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'], 'text_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'category' => ['required','in:verification,reputation,events,community,staff,achievement,promotional,custom'],
            'visibility' => ['required','in:public,members,tenant_members,event_attendees,staff,private'],
            'issuance_type' => ['required','in:manual,automatic,verification,membership'], 'verification_type' => ['nullable','string','max:80'],
            'event_count' => ['nullable','integer','min:1','max:10000'], 'expires_after_days' => ['nullable','integer','min:1','max:3650'], 'is_system_reserved' => ['nullable','boolean'],
        ]);
        $slug = Str::slug($data['slug'] ?: $data['name']);
        if (Badge::whereNull('tenant_id')->where('scope', Badge::SCOPE_GLOBAL)->where('slug', $slug)->exists()) throw ValidationException::withMessages(['slug' => 'That global badge slug is already in use.']);
        if ($data['issuance_type'] === Badge::ISSUE_MEMBERSHIP) throw ValidationException::withMessages(['issuance_type' => 'Membership badges belong to a specific club or organization.']);
        if ($data['issuance_type'] === Badge::ISSUE_VERIFICATION && blank($data['verification_type'] ?? null)) throw ValidationException::withMessages(['verification_type' => 'Choose the verification record type that grants this badge.']);
        if ($data['issuance_type'] === Badge::ISSUE_AUTOMATIC && empty($data['event_count'])) throw ValidationException::withMessages(['event_count' => 'Enter the number of unique event check-ins required for this badge.']);
        $criteria = match ($data['issuance_type']) { Badge::ISSUE_VERIFICATION => ['verification_type' => trim((string)$data['verification_type'])], Badge::ISSUE_AUTOMATIC => ['event_count' => (int)$data['event_count']], default => null };
        $badge = Badge::create(['tenant_id'=>null,'scope'=>Badge::SCOPE_GLOBAL,'name'=>trim($data['name']),'slug'=>$slug,'description'=>$data['description']??null,'icon'=>$data['icon']??null,'badge_color'=>$data['badge_color'],'text_color'=>$data['text_color'],'category'=>$data['category'],'visibility'=>$data['visibility'],'issuance_type'=>$data['issuance_type'],'criteria'=>$criteria,'expires_after_days'=>$data['expires_after_days']??null,'is_active'=>true,'is_system_reserved'=>$request->boolean('is_system_reserved'),'created_by'=>$request->user()->id]);
        Audit::write('badge.global.created', $badge, after: $badge->toArray(), request: $request);
        return back()->with('status', 'Global badge created.');
    }

    public function update(Request $request, Badge $badge): RedirectResponse
    {
        abort_unless($badge->isGlobal(), 404); $data=$request->validate(['is_active'=>['required','boolean']]); $before=$badge->toArray(); $badge->update(['is_active'=>(bool)$data['is_active']]);
        Audit::write('badge.global.status', $badge, before:$before, after:$badge->fresh()->toArray(), request:$request); return back()->with('status','Global badge status updated.');
    }

    public function assign(Request $request, Badge $badge, BadgeService $badges): RedirectResponse
    {
        abort_unless($badge->isGlobal(),404); abort_unless($badge->is_active,422,'This badge is inactive.'); abort_unless($badge->issuance_type===Badge::ISSUE_MANUAL,422,'Only manual badges can be directly assigned.');
        $data=$request->validate(['email'=>['required','email'],'expires_at'=>['nullable','date','after:now']]); $user=User::where('email',$data['email'])->firstOrFail(); $assignment=$badges->awardManual($badge,$user,$request->user(),$data['expires_at']??null);
        Audit::write('badge.global.assigned',$assignment,after:$assignment->toArray(),request:$request); return back()->with('status',$badge->name.' assigned to '.$user->email.'.');
    }

    public function revoke(Request $request, Badge $badge, UserBadge $assignment, BadgeService $badges): RedirectResponse
    {
        abort_unless($badge->isGlobal(),404); abort_unless($assignment->badge_id===$badge->id&&$assignment->tenant_id===null,404); abort_unless($badge->issuance_type===Badge::ISSUE_MANUAL,422,'System-managed badges follow their configured criteria and cannot be manually revoked.');
        $data=$request->validate(['reason'=>['required','string','max:1000']]); $before=$assignment->toArray(); $badges->revoke($assignment,$request->user(),$data['reason']); Audit::write('badge.global.revoked',$assignment,before:$before,after:$assignment->fresh()->toArray(),request:$request); return back()->with('status','Global badge revoked.');
    }
}
