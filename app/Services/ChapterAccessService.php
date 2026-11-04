<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunityGroup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChapterAccessService
{
    public function canOperate(Tenant $tenant, CommunityGroup $chapter, ?User $user): bool
    {
        if (! $user || ! $tenant->isOrganization() || ! $chapter->isChapter() || (int) $chapter->tenant_id !== (int) $tenant->id || $chapter->status !== 'active') {
            return false;
        }
        if ($this->isParentManager($tenant, $user)) return true;
        return (int) $chapter->chapter_lead_user_id === (int) $user->id && $this->isActiveTenantMember($tenant->id, $user->id);
    }

    public function isParentManager(Tenant $tenant, ?User $user): bool
    {
        if (! $user) return false;
        if ($user->is_platform_admin) return true;
        $role = DB::table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');
        return in_array($role, ['owner', 'admin', 'manager'], true);
    }

    public function canView(Tenant $tenant, CommunityGroup $chapter, ?User $user): bool
    {
        if (! $tenant->isOrganization() || ! $chapter->isChapter() || (int) $chapter->tenant_id !== (int) $tenant->id || $chapter->status !== 'active') return false;
        if ($chapter->visibility === 'public') return true;
        if (! $user) return false;
        if ($user->is_platform_admin) return true;
        if (! $this->isActiveTenantMember($tenant->id, $user->id)) return false;
        if ($chapter->visibility === 'tenant') return true;

        return $this->isActiveChapterMember($chapter->id, $user->id);
    }

    public function isActiveChapterMember(int $chapterId, int $userId): bool
    {
        return DB::table('community_group_members')
            ->where('community_group_id', $chapterId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function isActiveTenantMember(int $tenantId, int $userId): bool
    {
        return DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}
