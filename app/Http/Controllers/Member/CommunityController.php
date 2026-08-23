<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityPollVote;
use App\Models\CommunityPost;
use App\Models\CommunityReaction;
use App\Services\MemberPrivacy;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CommunityController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        $posts = CommunityPost::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where(function ($plain): void {
                    $plain->whereNull('group_id')->whereNull('event_id');
                })->orWhere('share_to_club_wall', true);
            })
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
            'canModerate' => $this->canModerate($request, (int) $tenant->id),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        CommunityPost::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'post_type' => 'status',
            'visibility' => 'members',
            'share_to_club_wall' => true,
            'body' => trim($data['body']),
            'status' => 'active',
        ]);

        return back()->with('status', 'Posted to the private community.');
    }

    public function media(Request $request, CommunityPost $post, TenantContext $context, MemberPrivacy $privacy): StreamedResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMemberCanAccessPost($request, $post, (int) $tenant->id, $privacy);
        abort_unless($post->media_path && Storage::disk('local')->exists($post->media_path), 404);

        return Storage::disk('local')->response($post->media_path, null, [
            'Content-Type' => $post->media_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function comment(Request $request, CommunityPost $post, TenantContext $context, MemberPrivacy $privacy): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMemberCanAccessPost($request, $post, (int) $tenant->id, $privacy);
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

    public function react(Request $request, CommunityPost $post, TenantContext $context, MemberPrivacy $privacy): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMemberCanAccessPost($request, $post, (int) $tenant->id, $privacy);
        $data = $request->validate(['reaction' => ['required', 'in:like,love,celebrate,support']]);

        CommunityReaction::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            ['tenant_id' => $tenant->id, 'reaction' => $data['reaction']]
        );

        return back();
    }

    public function removeReaction(Request $request, CommunityPost $post, TenantContext $context, MemberPrivacy $privacy): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMemberCanAccessPost($request, $post, (int) $tenant->id, $privacy);

        CommunityReaction::where([
            'tenant_id' => $tenant->id,
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
        ])->delete();

        return back();
    }

    public function vote(Request $request, CommunityPost $post, TenantContext $context, MemberPrivacy $privacy): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertMemberCanAccessPost($request, $post, (int) $tenant->id, $privacy);
        $options = is_array($post->poll_options) ? array_values($post->poll_options) : [];
        if ($options === []) {
            throw ValidationException::withMessages(['option_index' => 'This post does not contain an active poll.']);
        }

        $data = $request->validate(['option_index' => ['required', 'integer', 'min:0', 'max:'.(count($options) - 1)]]);
        CommunityPollVote::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            ['option_index' => (int) $data['option_index']]
        );

        return back()->with('status', 'Vote recorded.');
    }

    public function destroy(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, (int) $tenant->id);
        abort_unless((int) $post->user_id === (int) $request->user()->id || $this->canModerate($request, (int) $tenant->id), 403);

        $post->update(['status' => 'removed']);

        return back()->with('status', 'Post removed.');
    }

    public function destroyComment(Request $request, CommunityComment $comment, TenantContext $context, MemberPrivacy $privacy): RedirectResponse
    {
        $tenant = $context->requireTenant();
        abort_unless((int) $comment->tenant_id === (int) $tenant->id, 404);
        $post = CommunityPost::query()->findOrFail((int) $comment->post_id);
        if (! $this->canModerate($request, (int) $tenant->id)) {
            $this->assertMemberCanAccessPost($request, $post, (int) $tenant->id, $privacy);
        }
        abort_unless((int) $comment->user_id === (int) $request->user()->id || $this->canModerate($request, (int) $tenant->id), 403);

        $comment->update(['status' => 'removed']);

        return back();
    }

    public function pin(Request $request, CommunityPost $post, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $this->assertPostTenant($post, (int) $tenant->id);
        abort_unless($this->canModerate($request, (int) $tenant->id), 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back();
    }

    private function assertMemberCanAccessPost(Request $request, CommunityPost $post, int $tenantId, MemberPrivacy $privacy): void
    {
        $this->assertPostTenant($post, $tenantId);
        abort_unless($privacy->canAccessPost($tenantId, $request->user(), $post), 404);
    }

    private function assertPostTenant(CommunityPost $post, int $tenantId): void
    {
        abort_unless((int) $post->tenant_id === $tenantId && $post->status === 'active', 404);
    }

    private function canModerate(Request $request, int $tenantId): bool
    {
        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;

        return $membership && in_array((string) $membership->role, ['owner', 'admin', 'manager'], true);
    }
}
