<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(TenantContext $context)
    {
        $tenant = $context->requireTenant();

        return view('tenant.manage.staff', [
            'tenant' => $tenant,
            'staff' => $tenant->users()->wherePivotIn('role', ['owner', 'admin', 'manager', 'staff', 'checkin'])->get(),
            'invites' => DB::table('tenant_invitations')->where('tenant_id', $tenant->id)->whereNull('accepted_at')->latest()->get(),
        ]);
    }

    public function invite(Request $request, TenantContext $context)
    {
        $tenant = $context->requireTenant();
        $this->assertCanManageStaff($request, $tenant->id);

        $data = $request->validate([
            'email' => 'required|email|max:190',
            'role' => 'required|in:admin,manager,staff,checkin',
        ]);

        $email = strtolower(trim($data['email']));
        $existing = $tenant->users()->whereRaw('LOWER(users.email) = ?', [$email])->first();
        if ($existing) {
            abort_if($existing->pivot->role === 'owner', 422, 'The organization owner role cannot be replaced by an invitation.');
        }

        DB::table('tenant_invitations')
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->delete();

        $token = bin2hex(random_bytes(32));
        DB::table('tenant_invitations')->insert([
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role' => $data['role'],
            // The invitation URL is a bearer credential. Persist only its
            // SHA-256 digest so a database read does not reveal usable links.
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Invite created: '.url('/staff-invite/'.$token));
    }

    public function remove(Request $request, TenantContext $context, int $user)
    {
        $tenant = $context->requireTenant();
        $this->assertCanManageStaff($request, $tenant->id);

        $membership = $tenant->users()->where('users.id', $user)->first()?->pivot;
        abort_unless($membership, 404);
        abort_if($membership->role === 'owner', 422, 'The organization owner cannot be removed.');

        $tenant->users()->detach($user);

        return back()->with('status', 'Staff access removed.');
    }

    public function accept(Request $request, TenantContext $context, string $token)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/i', $token) === 1, 404);
        $activeTenantId = $context->check() ? $context->id() : null;

        $tenantId = DB::transaction(function () use ($request, $token, $activeTenantId): int {
            $digest = hash('sha256', strtolower($token));
            $invite = DB::table('tenant_invitations')
                ->whereNull('accepted_at')
                ->where(function ($query) use ($digest, $token): void {
                    $query->where('token', $digest)
                        // Backward compatibility for invitations created by
                        // 1.0.8 and earlier, which stored the raw token.
                        ->orWhere('token', $token);
                })
                ->lockForUpdate()
                ->first();

            abort_unless($invite && now()->lte($invite->expires_at), 404);
            if ($activeTenantId !== null) {
                abort_unless((int) $invite->tenant_id === (int) $activeTenantId, 404);
            }
            abort_unless(strtolower($request->user()->email) === strtolower($invite->email), 403, 'Sign in with the invited email address.');

            $current = DB::table('tenant_users')
                ->where('tenant_id', $invite->tenant_id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            abort_if($current && $current->role === 'owner', 422, 'An owner membership cannot be replaced by a staff invitation.');

            DB::table('tenant_users')->updateOrInsert(
                ['tenant_id' => $invite->tenant_id, 'user_id' => $request->user()->id],
                [
                    'role' => $invite->role,
                    'status' => 'active',
                    'created_at' => $current?->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('tenant_invitations')
                ->where('id', $invite->id)
                ->update(['accepted_at' => now(), 'updated_at' => now()]);

            return (int) $invite->tenant_id;
        });

        $domain = DB::table('tenant_domains')
            ->where('tenant_id', $tenantId)
            ->where('is_primary', 1)
            ->value('domain');

        return $domain
            ? redirect('https://'.$domain.'/manage')->with('status', 'Staff invitation accepted.')
            : redirect()->route('organizations.index')->with('status', 'Staff invitation accepted.');
    }

    private function assertCanManageStaff(Request $request, int $tenantId): void
    {
        if ($request->user()->is_platform_admin) {
            return;
        }

        $membership = $request->user()->tenants()->whereKey($tenantId)->first()?->pivot;
        abort_unless(
            $membership
            && $membership->status === 'active'
            && in_array($membership->role, ['owner', 'admin'], true),
            403,
            'Only organization owners and administrators can change staff access.'
        );
    }
}
