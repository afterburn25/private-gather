<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReaction;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommunityController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        $posts = CommunityPost::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with([
                'user:id,name,display_name',
                'comments' => fn ($query) => $query->where('status', 'active')->oldest()->with('user:id,name,display_name'),
                'reactions',
            ])
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(25);

        return view('member.community.index', [
            'tenant' => $tenant,
            'posts' => $posts,
            'canModerate' => $this->canModerate($request, $tenant->id),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        CommunityPost::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to the private community.');
    }

    public function comment(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, $tenant->id);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        CommunityComment::create([
            'tenant_id' => $tenant->id,
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back();
    }

    public function react(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, $tenant->id);
        $data = $request->validate(['reaction' => ['required', 'in:like,love,celebrate,support']]);

        CommunityReaction::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            ['tenant_id' => $tenant->id, 'reaction' => $data['reaction']]
        );

        return back();
    }

    public function removeReaction(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, $tenant->id);

        CommunityReaction::where([
            'tenant_id' => $tenant->id,
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
        ])->delete();

        return back();
    }

    public function destroy(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, $tenant->id);
        abort_unless((int) $post->user_id === (int) $request->user()->id || $this->canModerate($request, $tenant->id), 403);

        $post->update(['status' => 'removed']);

        return back()->with('status', 'Post removed.');
    }

    public function destroyComment(Request $request, CommunityComment $comment, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $comment->tenant_id === (int) $tenant->id, 404);
        abort_unless((int) $comment->user_id === (int) $request->user()->id || $this->canModerate($request, $tenant->id), 403);

        $comment->update(['status' => 'removed']);

        return back();
    }

    public function pin(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, $tenant->id);
        abort_unless($this->canModerate($request, $tenant->id), 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back();
    }

    private function assertPostTenant(CommunityPost $post, int $tenantId): void
    {
        abort_unless((int) $post->tenant_id === $tenantId && $post->status === 'active', 404);
    }

    private function canModerate(Request $request, int $tenantId): bool
    {
        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;

        return $membership && in_array($membership->role, ['owner', 'admin', 'manager'], true);
    }
}
