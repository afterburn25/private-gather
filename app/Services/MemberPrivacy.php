<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunityGroupMember;
use App\Models\CommunityPost;
use App\Models\MemberConnection;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MemberPrivacy
{
    public function activeMember(int $tenantId, int $userId): bool
    {
        return DB::table('tenant_users')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'active')->exists();
    }

    public function blockedEitherDirection(int $one, int $two): bool
    {
        return DB::table('user_blocks')->where(function ($query) use ($one, $two): void {
            $query->where('user_id', $one)->where('blocked_user_id', $two);
        })->orWhere(function ($query) use ($one, $two): void {
            $query->where('user_id', $two)->where('blocked_user_id', $one);
        })->exists();
    }

    public function connected(int $tenantId, int $one, int $two): bool
    {
        [$low, $high] = MemberConnection::orderedPair($one, $two);
        return MemberConnection::query()
            ->where('tenant_id', $tenantId)
            ->where('user_one_id', $low)
            ->where('user_two_id', $high)
            ->where('status', 'accepted')
            ->exists();
    }

    public function canViewProfile(int $tenantId, User $viewer, User $target): bool
    {
        if ((int) $viewer->id === (int) $target->id) {
            return true;
        }
        if (! $this->activeMember($tenantId, (int) $target->id) || $this->blockedEitherDirection((int) $viewer->id, (int) $target->id)) {
            return false;
        }
        $target->loadMissing('profile');
        if (! $target->profile?->discoverable) {
            return $this->connected($tenantId, (int) $viewer->id, (int) $target->id);
        }
        $visibility = (string) data_get($target->profile?->visibility, 'profile', 'members');
        return match ($visibility) {
            'private' => false,
            'connections' => $this->connected($tenantId, (int) $viewer->id, (int) $target->id),
            default => true,
        };
    }

    public function canMessage(int $tenantId, User $sender, User $target): bool
    {
        if ((int) $sender->id === (int) $target->id || ! $this->activeMember($tenantId, (int) $target->id)) {
            return false;
        }
        if ($this->blockedEitherDirection((int) $sender->id, (int) $target->id)) {
            return false;
        }
        $target->loadMissing('profile');
        return match ((string) ($target->profile?->message_permissions ?: 'members')) {
            'none' => false,
            'connections' => $this->connected($tenantId, (int) $sender->id, (int) $target->id),
            default => true,
        };
    }

    public function canAccessPost(int $tenantId, User $viewer, CommunityPost $post): bool
    {
        if ((int) $post->tenant_id !== $tenantId || $post->status !== 'active') {
            return false;
        }

        $authorId = (int) ($post->user_id ?? 0);
        if ($authorId > 0 && $this->blockedEitherDirection((int) $viewer->id, $authorId)) {
            return false;
        }

        if ($post->visibility === 'private' && $authorId !== (int) $viewer->id) {
            return false;
        }
        if ($post->visibility === 'connections'
            && $authorId !== (int) $viewer->id
            && ! $this->connected($tenantId, (int) $viewer->id, $authorId)) {
            return false;
        }

        if ($post->group_id) {
            $group = $post->group()->where('tenant_id', $tenantId)->where('status', 'active')->first();
            if (! $group) {
                return false;
            }
            if ($group->visibility === 'private'
                && ! CommunityGroupMember::query()->where('group_id', $group->id)->where('user_id', $viewer->id)->where('status', 'active')->exists()) {
                return false;
            }
        }

        if ($post->event_id) {
            $event = $post->event()->where('tenant_id', $tenantId)->where('status', 'published')->first();
            if (! $event) {
                return false;
            }
            if (in_array($event->visibility, ['private', 'invite_only'], true)
                && ! $event->rsvps()->where('user_id', $viewer->id)->where('status', 'approved')->exists()) {
                return false;
            }
        }

        return true;
    }

    public function fieldVisible(?Profile $profile, string $field, int $tenantId, int $viewerId, int $targetId): bool
    {
        if ($viewerId === $targetId) {
            return true;
        }
        $visibility = (string) data_get($profile?->visibility, $field, data_get($profile?->visibility, 'profile', 'members'));
        return match ($visibility) {
            'private' => false,
            'connections' => $this->connected($tenantId, $viewerId, $targetId),
            default => true,
        };
    }
}
