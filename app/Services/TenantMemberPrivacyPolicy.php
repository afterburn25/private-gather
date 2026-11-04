<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class TenantMemberPrivacyPolicy
{
    public const DIRECTORY_DISABLED = 'disabled';
    public const DIRECTORY_MEMBERS = 'members';
    public const DIRECTORY_VERIFIED = 'verified';
    public const DIRECTORY_PUBLIC = 'public';

    public function directoryMode(Tenant $tenant): string
    {
        $configured = data_get($tenant->settings, 'member_directory_visibility');
        if ($configured === null || $configured === '') {
            return self::DIRECTORY_MEMBERS;
        }

        return in_array($configured, [
            self::DIRECTORY_DISABLED,
            self::DIRECTORY_MEMBERS,
            self::DIRECTORY_VERIFIED,
            self::DIRECTORY_PUBLIC,
        ], true) ? $configured : self::DIRECTORY_DISABLED;
    }

    public function viewerIsActiveMember(Tenant $tenant, int $userId): bool
    {
        return DB::table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function directoryCanReturnProfiles(Tenant $tenant): bool
    {
        // Identity verification is intentionally not inferred from email
        // verification. Until a real identity-verification source exists,
        // verified-only directories remain fail-closed.
        return in_array($this->directoryMode($tenant), [
            self::DIRECTORY_MEMBERS,
            self::DIRECTORY_PUBLIC,
        ], true);
    }

    public function directoryBoundaryMessage(Tenant $tenant): string
    {
        return match ($this->directoryMode($tenant)) {
            self::DIRECTORY_DISABLED => 'This organization has disabled its member directory.',
            self::DIRECTORY_VERIFIED => 'This directory is limited to verified members. No identity-verification source is configured, so profiles remain hidden.',
            self::DIRECTORY_PUBLIC => 'This organization permits public directory visibility, but this Private Gather member-discovery screen remains authenticated and community-scoped. Individual privacy choices still apply.',
            default => 'Results are limited to eligible active members of this organization and each member’s privacy choices remain authoritative.',
        };
    }
}
