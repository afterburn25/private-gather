<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class TenantMembershipAccessService
{
    public function mode(Tenant $tenant): string
    {
        $mode = (string) (($tenant->settings ?? [])['membership_access'] ?? 'application');
        return in_array($mode, ['open', 'application', 'invite_only'], true) ? $mode : 'invite_only';
    }

    public function minimumAge(Tenant $tenant): int
    {
        return max(18, min(99, (int) (($tenant->settings ?? [])['minimum_age'] ?? 18)));
    }

    public function allowedProfileTypes(Tenant $tenant): array
    {
        $configured = (array) (($tenant->settings ?? [])['membership_profiles'] ?? ['couple', 'individual']);
        $allowed = array_values(array_unique(array_intersect(['couple', 'individual'], $configured)));
        return $allowed !== [] ? $allowed : [];
    }

    public function assertEligible(Tenant $tenant, User $user, ?string $requestedProfileType = null): string
    {
        if (! $user->adult_confirmed_at || ! $user->date_of_birth || $user->date_of_birth->gt(now()->subYears($this->minimumAge($tenant)))) {
            throw ValidationException::withMessages([
                'membership' => 'You must meet this organization’s minimum age requirement of '.$this->minimumAge($tenant).'.',
            ]);
        }

        $actualProfileType = $user->profile?->profile_type;
        $profileType = $actualProfileType ?: $requestedProfileType;
        if (! in_array($profileType, ['couple', 'individual'], true)) {
            throw ValidationException::withMessages([
                'profile_type' => 'Complete your Private Gather profile before joining this organization.',
            ]);
        }

        if ($actualProfileType && $requestedProfileType && $actualProfileType !== $requestedProfileType) {
            throw ValidationException::withMessages([
                'profile_type' => 'Your membership profile type must match your Private Gather profile.',
            ]);
        }

        if (! in_array($profileType, $this->allowedProfileTypes($tenant), true)) {
            throw ValidationException::withMessages([
                'profile_type' => ucfirst($profileType).' profiles are not currently eligible for membership in this organization.',
            ]);
        }

        return $profileType;
    }
}
