<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberLike;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class HostedMatchController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();

        $outgoing = MemberLike::query()
            ->where('tenant_id', $tenant->id)
            ->where('liker_user_id', $viewer->id)
            ->where('status', 'active')
            ->get();
        $likedIds = $outgoing->pluck('liked_user_id')->map(fn ($id) => (int) $id)->all();
        $matchedIds = $outgoing->whereNotNull('matched_at')->pluck('liked_user_id')->map(fn ($id) => (int) $id)->all();

        $candidates = $tenant->users()
            ->wherePivot('status', 'active')
            ->where('users.id', '<>', $viewer->id)
            ->whereHas('profile', fn ($query) => $query->where('discoverable', true))
            ->with('profile:user_id,city,region,headline,interests,discoverable')
            ->orderByDesc('last_login_at')
            ->orderBy('display_name')
            ->limit(80)
            ->get(['users.id', 'users.name', 'users.display_name', 'users.last_login_at']);

        $matches = $candidates->whereIn('id', $matchedIds)->values();

        return view('member.matches.index', compact('tenant', 'viewer', 'candidates', 'likedIds', 'matchedIds', 'matches'));
    }

    public function like(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();
        abort_if((int) $viewer->id === (int) $user->id, 422, 'You cannot like yourself.');
        abort_unless($this->isActiveTenantMember($tenant->id, $user->id), 404);

        $matched = DB::transaction(function () use ($tenant, $viewer, $user): bool {
            $like = MemberLike::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'liker_user_id' => $viewer->id,
                    'liked_user_id' => $user->id,
                ],
                ['status' => 'active', 'source' => 'network', 'matched_at' => null]
            );

            $reverse = MemberLike::query()
                ->where('tenant_id', $tenant->id)
                ->where('liker_user_id', $user->id)
                ->where('liked_user_id', $viewer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $reverse) {
                return false;
            }

            $matchedAt = $reverse->matched_at ?: now();
            $reverse->update(['matched_at' => $matchedAt]);
            $like->update(['matched_at' => $matchedAt]);

            return true;
        });

        return back()->with('status', $matched ? 'It’s a match. You both liked each other.' : 'Like saved privately. They will not be told unless the interest is mutual.');
    }

    public function unlike(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $viewer = $request->user();

        DB::transaction(function () use ($tenant, $viewer, $user): void {
            MemberLike::query()
                ->where('tenant_id', $tenant->id)
                ->where('liker_user_id', $viewer->id)
                ->where('liked_user_id', $user->id)
                ->delete();

            MemberLike::query()
                ->where('tenant_id', $tenant->id)
                ->where('liker_user_id', $user->id)
                ->where('liked_user_id', $viewer->id)
                ->update(['matched_at' => null]);
        });

        return back()->with('status', 'Like removed.');
    }

    private function isActiveTenantMember(int $tenantId, int $userId): bool
    {
        return User::query()->whereKey($userId)->whereHas('tenants', function ($query) use ($tenantId): void {
            $query->where('tenants.id', $tenantId)->where('tenant_users.status', 'active');
        })->exists();
    }
}
