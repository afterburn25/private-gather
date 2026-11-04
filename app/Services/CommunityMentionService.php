<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\NetworkCommunityComment;
use App\Models\NetworkCommunityPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CommunityMentionService
{
    public function tenantPost(CommunityPost $post): void
    {
        $this->processTenant('tenant_post', $post->id, (int) $post->tenant_id, (int) $post->user_id, $post->community_group_id ? (int) $post->community_group_id : null, (string) $post->body);
    }

    public function tenantComment(CommunityComment $comment, CommunityPost $post): void
    {
        $this->processTenant('tenant_comment', $comment->id, (int) $comment->tenant_id, (int) $comment->user_id, $post->community_group_id ? (int) $post->community_group_id : null, (string) $comment->body);
    }

    public function networkPost(NetworkCommunityPost $post): void
    {
        $this->processNetwork('network_post', $post->id, (int) $post->user_id, $post->network_community_group_id ? (int) $post->network_community_group_id : null, (string) $post->body);
    }

    public function networkComment(NetworkCommunityComment $comment, NetworkCommunityPost $post): void
    {
        $this->processNetwork('network_comment', $comment->id, (int) $comment->user_id, $post->network_community_group_id ? (int) $post->network_community_group_id : null, (string) $comment->body);
    }

    private function processTenant(string $sourceType, int $sourceId, int $tenantId, int $authorId, ?int $groupId, string $body): void
    {
        foreach ($this->names($body) as $name) {
            $targets = DB::table('users')
                ->join('tenant_users', 'tenant_users.user_id', '=', 'users.id')
                ->where('tenant_users.tenant_id', $tenantId)
                ->where('tenant_users.status', 'active')
                ->where('users.status', 'active')
                ->where('users.id', '!=', $authorId)
                ->whereRaw('LOWER(COALESCE(users.display_name, users.name)) = ?', [mb_strtolower($name)])
                ->get(['users.id']);

            foreach ($targets as $target) {
                $targetId = (int) $target->id;
                if ($groupId && ! $this->activeTenantGroupMember($groupId, $targetId)) continue;
                if ($this->blocked($authorId, $targetId)) continue;
                $this->recordAndNotify('tenant', $sourceType, $sourceId, $tenantId, $authorId, $targetId);
            }
        }
    }

    private function processNetwork(string $sourceType, int $sourceId, int $authorId, ?int $groupId, string $body): void
    {
        foreach ($this->names($body) as $name) {
            $targets = DB::table('users')
                ->where('status', 'active')
                ->where('id', '!=', $authorId)
                ->whereRaw('LOWER(COALESCE(display_name, name)) = ?', [mb_strtolower($name)])
                ->get(['id']);

            foreach ($targets as $target) {
                $targetId = (int) $target->id;
                if ($groupId && ! $this->activeNetworkGroupMember($groupId, $targetId)) continue;
                if ($this->blocked($authorId, $targetId)) continue;
                $this->recordAndNotify('network', $sourceType, $sourceId, null, $authorId, $targetId);
            }
        }
    }

    private function recordAndNotify(string $scope, string $sourceType, int $sourceId, ?int $tenantId, int $authorId, int $targetId): void
    {
        $inserted = DB::table('community_mentions')->insertOrIgnore([
            'scope' => $scope,
            'tenant_id' => $tenantId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'author_id' => $authorId,
            'mentioned_user_id' => $targetId,
            'notified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted !== 1) return;

        app(ProductCompletionService::class)->notify($targetId, 'community.mention', [
            'label' => 'You were mentioned in a community conversation',
            'category' => 'community',
            'scope' => $scope,
            'tenant_id' => $tenantId,
        ]);
    }

    private function names(string $body): Collection
    {
        preg_match_all('/@\[([^\]\r\n]{1,80})\]/u', $body, $matches);
        return collect($matches[1] ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name))->take(10)->values();
    }

    private function blocked(int $a, int $b): bool
    {
        return DB::table('user_blocks')->where(function ($query) use ($a, $b): void {
            $query->where('user_id', $a)->where('blocked_user_id', $b);
        })->orWhere(function ($query) use ($a, $b): void {
            $query->where('user_id', $b)->where('blocked_user_id', $a);
        })->exists();
    }

    private function activeTenantGroupMember(int $groupId, int $userId): bool
    {
        return DB::table('community_group_members')->where('community_group_id', $groupId)->where('user_id', $userId)->where('status', 'active')->exists();
    }

    private function activeNetworkGroupMember(int $groupId, int $userId): bool
    {
        return DB::table('network_community_group_members')->where('network_community_group_id', $groupId)->where('user_id', $userId)->where('status', 'active')->exists();
    }
}
