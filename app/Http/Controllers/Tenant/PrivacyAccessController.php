<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PrivacyAccessController extends Controller
{
    private const POLICY_KEYS = [
        'membership_access',
        'minimum_age',
        'membership_profiles',
        'event_access_default',
        'venue_address_visibility',
        'network_visibility',
        'member_directory_visibility',
        'event_attendance_visibility',
        'marketplace_enabled',
    ];

    public function edit(TenantContext $context): View
    {
        $tenant = $context->requireTenant();

        return view('tenant.manage.privacy-access', [
            'tenant' => $tenant,
            'policy' => $this->policySnapshot($tenant),
        ]);
    }

    public function update(Request $request, TenantContext $context): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $data = $request->validate([
            'membership_access' => ['required', 'in:open,application,invite_only'],
            'minimum_age' => ['required', 'integer', 'min:18', 'max:99'],
            'membership_profiles' => ['required', 'array', 'min:1', 'max:2'],
            'membership_profiles.*' => ['required', 'distinct', 'in:couple,individual'],
            'event_access_default' => ['required', 'in:public,members,approval,invite_only'],
            'venue_address_visibility' => ['required', 'in:public,members,approved_or_ticketed'],
            'network_visibility' => ['required', 'in:listed,private,invite_only'],
            'member_directory_visibility' => ['required', 'in:disabled,members,verified,public'],
            'event_attendance_visibility' => ['required', 'in:private,members,attendees'],
        ]);

        if ($tenant->type === Tenant::TYPE_PRIVATE_HOST && $data['network_visibility'] === 'listed') {
            throw ValidationException::withMessages([
                'network_visibility' => 'Private Host sites cannot be listed in Private Gather network discovery. Choose Private / unlisted or Invitation only.',
            ]);
        }

        $before = $this->policySnapshot($tenant);

        $updatedTenant = DB::transaction(function () use ($tenant, $data): Tenant {
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $settings = is_array($locked->settings) ? $locked->settings : [];
            $settings['membership_access'] = $data['membership_access'];
            $settings['minimum_age'] = (int) $data['minimum_age'];
            $settings['membership_profiles'] = array_values($data['membership_profiles']);
            $settings['event_access_default'] = $data['event_access_default'];
            $settings['venue_address_visibility'] = $data['venue_address_visibility'];
            $settings['network_visibility'] = $data['network_visibility'];
            $settings['member_directory_visibility'] = $data['member_directory_visibility'];
            $settings['event_attendance_visibility'] = $data['event_attendance_visibility'];
            $settings['marketplace_enabled'] = $data['network_visibility'] === 'listed';
            $locked->settings = $settings;
            $locked->save();

            return $locked->fresh();
        });

        $after = $this->policySnapshot($updatedTenant);
        Audit::write(
            'tenant.privacy_access.updated',
            $updatedTenant,
            before: $before,
            after: $after,
            tenantId: $updatedTenant->id,
            request: $request,
        );

        return back()->with('status', 'Privacy, access and discovery settings updated.');
    }

    private function policySnapshot(Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $defaults = [
            'membership_access' => 'application',
            'minimum_age' => 18,
            'membership_profiles' => ['couple', 'individual'],
            'event_access_default' => 'members',
            'venue_address_visibility' => 'approved_or_ticketed',
            'network_visibility' => $tenant->networkVisibility(),
            'member_directory_visibility' => 'members',
            'event_attendance_visibility' => 'private',
            'marketplace_enabled' => $tenant->isNetworkListed(),
        ];

        return collect(self::POLICY_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => $settings[$key] ?? $defaults[$key]])
            ->all();
    }
}
