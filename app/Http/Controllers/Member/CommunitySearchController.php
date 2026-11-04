<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityPost;
use App\Models\NetworkCommunityGroup;
use App\Models\NetworkCommunityPost;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommunitySearchController extends Controller
{
    public function tenant(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();
        $queryText = $this->queryText($request);
        $blocked = $this->blockedUserIds((int) $request->user()->id);
        $membership = $request->user()->tenants()->whereKey($tenant->id)->first()?->pivot;
        $manager = (bool) ($membership && $membership->status === 'active' && in_array($membership->role, ['owner', 'admin', 'manager'], true));

        $posts = $this->emptyPaginator($request);
        $groups = collect();
        if (mb_strlen($queryText) >= 2) {
            $postsQuery = CommunityPost::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('body', 'like', '%'.$this->escapeLike($queryText).'%')
                ->when($blocked !== [], fn ($builder) => $builder->whereNotIn('user_id', $blocked))
                ->with(['user:id,name,display_name', 'group:id,name,visibility', 'event:id,title,starts_at']);

            if (! $manager) {
                $postsQuery->where(function ($visible) use ($request): void {
                    $visible->whereNull('community_group_id')
                        ->orWhereHas('group', function ($groups) use ($request): void {
                            $groups->where('status', 'active')->where(function ($allowed) use ($request): void {
                                $allowed->whereIn('visibility', ['tenant', 'public'])
                                    ->orWhereHas('members', fn ($members) => $members
                                        ->where('users.id', $request->user()->id)
                                        ->where('community_group_members.status', 'active'));
                            });
                        });
                });
            }
            $posts = $postsQuery->latest()->paginate(25)->withQueryString();

            $groups = CommunityGroup::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where(function ($match) use ($queryText): void {
                    $match->where('name', 'like', '%'.$this->escapeLike($queryText).'%')
                        ->orWhere('description', 'like', '%'.$this->escapeLike($queryText).'%');
                })
                ->when(! $manager, fn ($builder) => $builder->where(function ($visible) use ($request): void {
                    $visible->whereIn('visibility', ['tenant', 'public'])
                        ->orWhereHas('members', fn ($members) => $members
                            ->where('users.id', $request->user()->id)
                            ->where('community_group_members.status', 'active'));
                }))
                ->orderBy('name')->limit(20)->get();
        }

        return view('member.community.search', [
            'scope' => 'tenant',
            'tenant' => $tenant,
            'queryText' => $queryText,
            'posts' => $posts,
            'groups' => $groups,
        ]);
    }

    public function network(Request $request, TenantContext $context): View
    {
        abort_unless(Edition::isHosted() && ! $context->check(), 404);
        $queryText = $this->queryText($request);
        $blocked = $this->blockedUserIds((int) $request->user()->id);
        $posts = $this->emptyPaginator($request);
        $groups = collect();

        if (mb_strlen($queryText) >= 2) {
            $posts = NetworkCommunityPost::query()
                ->where('status', 'active')
                ->where('body', 'like', '%'.$this->escapeLike($queryText).'%')
                ->when($blocked !== [], fn ($builder) => $builder->whereNotIn('user_id', $blocked))
                ->where(function ($visible) use ($request): void {
                    $visible->whereNull('network_community_group_id')
                        ->orWhereHas('group', fn ($group) => $group->whereHas('members', fn ($members) => $members
                            ->where('users.id', $request->user()->id)
                            ->where('network_community_group_members.status', 'active')));
                })
                ->with(['user:id,name,display_name', 'group:id,name', 'event:id,title,starts_at'])
                ->latest()->paginate(25)->withQueryString();

            $groups = NetworkCommunityGroup::query()
                ->where('status', 'active')
                ->where(function ($match) use ($queryText): void {
                    $match->where('name', 'like', '%'.$this->escapeLike($queryText).'%')
                        ->orWhere('description', 'like', '%'.$this->escapeLike($queryText).'%');
                })
                ->orderBy('name')->limit(20)->get();
        }

        return view('member.community.search', [
            'scope' => 'network',
            'tenant' => null,
            'queryText' => $queryText,
            'posts' => $posts,
            'groups' => $groups,
        ]);
    }

    private function queryText(Request $request): string
    {
        return trim(mb_substr((string) $request->query('q', ''), 0, 100));
    }

    private function blockedUserIds(int $userId): array
    {
        return DB::table('user_blocks')
            ->where('user_id', $userId)->pluck('blocked_user_id')
            ->merge(DB::table('user_blocks')->where('blocked_user_id', $userId)->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 25, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
