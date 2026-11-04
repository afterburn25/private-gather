<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantMemberPrivacyPolicy;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class MemberDirectoryController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, TenantMemberPrivacyPolicy $policy): View
    {
        $viewerId = (int) $request->user()->id;
        $blocked = DB::table('user_blocks')->where('user_id', $viewerId)->pluck('blocked_user_id')
            ->merge(DB::table('user_blocks')->where('blocked_user_id', $viewerId)->pluck('user_id'))
            ->unique()->map(fn ($id) => (int) $id)->values();

        $tenant = null;
        $directoryMode = 'network';
        $directoryBoundaryMessage = 'Only signed-in Private Gather members with discoverable profiles are shown. Member privacy, connections-only visibility, location choices and blocks always apply.';

        if ($context->check()) {
            $tenant = $context->requireTenant();
            abort_unless($policy->viewerIsActiveMember($tenant, $viewerId), 404);

            $users = $tenant->users()
                ->with('profile')
                ->wherePivot('status', 'active')
                ->where('users.id', '!=', $viewerId)
                ->whereNotIn('users.id', $blocked)
                ->whereHas('profile', fn ($query) => $query->where('discoverable', true));

            $directoryMode = $policy->directoryMode($tenant);
            $directoryBoundaryMessage = $policy->directoryBoundaryMessage($tenant);
            if (! $policy->directoryCanReturnProfiles($tenant)) {
                $users->whereRaw('1 = 0');
            } else {
                $this->applyProfileVisibility($users, $viewerId);
            }
        } else {
            abort_unless(Edition::isHosted(), 404);
            $users = User::query()
                ->with('profile')
                ->where('users.id', '!=', $viewerId)
                ->whereNotIn('users.id', $blocked)
                ->whereHas('profile', fn ($query) => $query->where('discoverable', true));
            $this->applyProfileVisibility($users, $viewerId);
        }

        if ($city = trim((string) $request->query('city'))) {
            // A city filter must not act as a side-channel for profiles whose
            // member chose hidden or region-only location visibility.
            $users->whereHas('profile', fn ($query) => $query->where('city', 'like', '%'.$city.'%'))
                ->where(function ($query): void {
                    $query->whereNotExists(function ($privacy): void {
                        $privacy->selectRaw('1')->from('user_privacy_settings')
                            ->whereColumn('user_privacy_settings.user_id', 'users.id');
                    })->orWhereExists(function ($privacy): void {
                        $privacy->selectRaw('1')->from('user_privacy_settings')
                            ->whereColumn('user_privacy_settings.user_id', 'users.id')
                            ->where('user_privacy_settings.location_visibility', 'city');
                    });
                });
        }

        if ($search = trim((string) $request->query('q'))) {
            $users->where(function ($query) use ($search): void {
                $query->where('users.display_name', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', fn ($profile) => $profile->where('headline', 'like', '%'.$search.'%'));
            });
        }

        $members = $users->orderByRaw('COALESCE(display_name, name)')->paginate(36)->withQueryString();
        $privacyByUser = DB::table('user_privacy_settings')
            ->whereIn('user_id', $members->getCollection()->pluck('id'))
            ->get()->keyBy('user_id');

        return view('member.directory', [
            'tenant' => $tenant,
            'members' => $members,
            'privacyByUser' => $privacyByUser,
            'directoryMode' => $directoryMode,
            'directoryBoundaryMessage' => $directoryBoundaryMessage,
        ]);
    }

    private function applyProfileVisibility($users, int $viewerId): void
    {
        $users->where(function ($query) use ($viewerId): void {
            $query->whereNotExists(function ($privacy): void {
                $privacy->selectRaw('1')
                    ->from('user_privacy_settings')
                    ->whereColumn('user_privacy_settings.user_id', 'users.id');
            })->orWhereExists(function ($privacy): void {
                $privacy->selectRaw('1')
                    ->from('user_privacy_settings')
                    ->whereColumn('user_privacy_settings.user_id', 'users.id')
                    ->where('user_privacy_settings.profile_visibility', 'members');
            })->orWhere(function ($connectionsOnly) use ($viewerId): void {
                $connectionsOnly->whereExists(function ($privacy): void {
                    $privacy->selectRaw('1')
                        ->from('user_privacy_settings')
                        ->whereColumn('user_privacy_settings.user_id', 'users.id')
                        ->where('user_privacy_settings.profile_visibility', 'connections');
                })->whereExists(function ($connection) use ($viewerId): void {
                    $connection->selectRaw('1')
                        ->from('user_connections')
                        ->where('user_connections.status', 'accepted')
                        ->where(function ($pair) use ($viewerId): void {
                            $pair->where(function ($forward) use ($viewerId): void {
                                $forward->where('user_connections.requester_id', $viewerId)
                                    ->whereColumn('user_connections.addressee_id', 'users.id');
                            })->orWhere(function ($reverse) use ($viewerId): void {
                                $reverse->where('user_connections.addressee_id', $viewerId)
                                    ->whereColumn('user_connections.requester_id', 'users.id');
                            });
                        });
                });
            });
        });
    }
}
