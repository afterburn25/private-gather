<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MemberTrust
{
    public function individualVerified(User $user): bool
    {
        return Verification::query()
            ->where('user_id', $user->id)
            ->where('type', 'age_identity')
            ->where('status', 'approved')
            ->whereNotNull('verified_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /** @return Collection<int,User> */
    public function participants(User $owner): Collection
    {
        $users = collect([$owner]);
        $profile = $owner->profile;
        if ($profile?->partner_user_id) {
            $partner = User::query()->find($profile->partner_user_id);
            if ($partner) {
                $users->push($partner);
            }
        }
        return $users;
    }

    public function participantForSlot(User $owner, string $slot): User
    {
        if ($slot === 'primary') {
            return $owner;
        }
        abort_unless($slot === 'partner', 404);
        $profile = $owner->profile;
        abort_unless($profile?->partner_user_id, 422, 'Link your partner account before couple verification.');
        $partner = User::query()->findOrFail($profile->partner_user_id);
        return $partner;
    }

    /** @return array{key:string,label:string,verified:bool,verified_count:int,required_count:int} */
    public function badge(User $owner): array
    {
        $participants = $this->participants($owner);
        $isCouple = $owner->profile?->profile_type === 'couple' || (bool) $owner->profile?->partner_user_id;
        $required = $isCouple ? 2 : 1;
        $verified = $participants->filter(fn (User $user): bool => $this->individualVerified($user))->count();

        if ($isCouple && $participants->count() < 2) {
            return ['key' => 'unverified', 'label' => 'Unverified · link partner', 'verified' => false, 'verified_count' => $verified, 'required_count' => 2];
        }
        if ($verified >= $required) {
            return ['key' => 'verified', 'label' => 'Verified Member', 'verified' => true, 'verified_count' => $verified, 'required_count' => $required];
        }
        if ($isCouple && $verified === 1) {
            return ['key' => 'partial', 'label' => 'Couple · 1 of 2 verified', 'verified' => false, 'verified_count' => 1, 'required_count' => 2];
        }
        return ['key' => 'unverified', 'label' => 'Unverified', 'verified' => false, 'verified_count' => $verified, 'required_count' => $required];
    }

    public function profileVerified(User $owner): bool
    {
        return $this->badge($owner)['verified'];
    }

    public function canJoinAnotherClub(User $user): bool
    {
        $activeMemberships = $user->tenants()
            ->where('tenants.type', Tenant::TYPE_CLUB)
            ->wherePivot('status', 'active')
            ->count();
        return $activeMemberships < 1 || $this->profileVerified($user);
    }

    public function refreshIndividualSummary(User $user): void
    {
        if (! DB::getSchemaBuilder()->hasColumn('users', 'verification_status')) {
            return;
        }
        $latest = Verification::query()
            ->where('user_id', $user->id)
            ->where('type', 'age_identity')
            ->latest('id')
            ->first();

        $verified = $this->individualVerified($user);
        DB::table('users')->where('id', $user->id)->update([
            'verification_status' => $verified ? 'verified' : ($latest?->status ?? 'unverified'),
            'verification_level' => $verified ? 'high' : null,
            'identity_verified_at' => $verified ? $latest?->verified_at : null,
            'identity_verification_expires_at' => $verified ? $latest?->expires_at : null,
        ]);
    }
}
