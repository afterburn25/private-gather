<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\MembershipApplication;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Edition;
use App\Support\LifestyleProfileOptions;
use App\Support\UsStates;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MemberAuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid credentials or account unavailable.'])->onlyInput('email');
        }

        $user = $request->user();
        $request->session()->regenerate();
        if ($user->two_factor_confirmed_at) {
            $request->session()->put([
                'auth.2fa_user' => $user->id,
                'auth.2fa_remember' => $request->boolean('remember'),
                'auth.2fa_intended' => $request->session()->pull('url.intended', '/dashboard'),
                'auth.2fa_issued_at' => now()->timestamp,
            ]);
            Auth::logout();
            return redirect()->route('two-factor.challenge');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        Audit::write('auth.login', $user, request: $request);
        return redirect()->intended(route('dashboard'));
    }

    public function registerForm(TenantContext $context)
    {
        abort_unless(Edition::registrationEnabled(), 404);
        return view('auth.register', [
            'tenant' => $context->tenant(),
            'lifestyleIdentities' => LifestyleProfileOptions::identities(),
            'relationshipStatuses' => LifestyleProfileOptions::relationshipStatuses(),
            'experienceLevels' => LifestyleProfileOptions::experienceLevels(),
            'lookingForOptions' => LifestyleProfileOptions::lookingFor(),
            'lifestyleInterestOptions' => LifestyleProfileOptions::interests(),
            'usStates' => UsStates::all(),
        ]);
    }

    public function register(Request $request, TenantContext $context)
    {
        abort_unless(Edition::registrationEnabled(), 404);

        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'username' => strtolower(trim((string) $request->input('username'))),
        ]);
        $identityKeys = array_keys(LifestyleProfileOptions::identities());
        $relationshipKeys = array_keys(LifestyleProfileOptions::relationshipStatuses());
        $experienceKeys = array_keys(LifestyleProfileOptions::experienceLevels());
        $lookingForKeys = array_keys(LifestyleProfileOptions::lookingFor());
        $interestKeys = array_keys(LifestyleProfileOptions::interests());

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => ['required','string','min:3','max:32','regex:/^[a-z0-9][a-z0-9._-]{2,31}$/','unique:users,username'],
            'email' => 'required|email|max:190|unique:users,email',
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'lifestyle_identity' => ['required', Rule::in($identityKeys)],
            'relationship_status' => ['nullable', Rule::in($relationshipKeys)],
            'experience_level' => ['nullable', Rule::in($experienceKeys)],
            'pronouns' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:120',
            'region' => ['nullable', Rule::in(array_keys(UsStates::all()))],
            'headline' => 'nullable|string|max:160',
            'looking_for' => 'nullable|array|max:8',
            'looking_for.*' => [Rule::in($lookingForKeys)],
            'lifestyle_interests' => 'nullable|array|max:16',
            'lifestyle_interests.*' => [Rule::in($interestKeys)],
            'boundaries' => 'nullable|string|max:2500',
            'application_note' => 'nullable|string|max:3000',
            'adult' => 'accepted',
            'terms' => 'accepted',
            'privacy' => 'accepted',
            'consent_culture' => 'accepted',
        ]);

        $tenant = $context->tenant();
        if (! $tenant && Edition::isSelfHosted()) {
            $tenant = $this->selfHostedTenant();
        }

        $requiresAccountApproval = Edition::isSelfHosted() && Edition::selfHostedRegistration() === 'approval';
        $membershipMode = $tenant ? (string) data_get($tenant->settings, 'membership_registration', 'approval') : 'open';
        $requiresMembershipApproval = $tenant !== null && $membershipMode !== 'open';
        $now = now();

        [$user, $membershipStatus] = DB::transaction(function () use ($data, $tenant, $requiresAccountApproval, $requiresMembershipApproval, $now): array {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'display_name' => $data['username'],
                'email' => $data['email'],
                'date_of_birth' => $data['date_of_birth'],
                'password' => $data['password'],
                'status' => $requiresAccountApproval ? 'pending' : 'active',
                'adult_confirmed_at' => $now,
                'terms_accepted_at' => $now,
                'privacy_accepted_at' => $now,
                'privacy_version' => config('platform.privacy_version', '1.0'),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'profile_type' => $data['lifestyle_identity'] === 'couple' ? 'couple' : 'individual',
                'lifestyle_identity' => $data['lifestyle_identity'],
                'relationship_status' => $data['relationship_status'] ?? null,
                'experience_level' => $data['experience_level'] ?? null,
                'pronouns' => $data['pronouns'] ?? null,
                'city' => $data['city'] ?? null,
                'region' => $data['region'] ?? null,
                'headline' => $data['headline'] ?? null,
                'looking_for' => array_values($data['looking_for'] ?? []),
                'lifestyle_interests' => array_values($data['lifestyle_interests'] ?? []),
                'boundaries' => $data['boundaries'] ?? null,
                'visibility' => [
                    'profile' => 'members',
                    'bio' => 'members',
                    'location' => 'members',
                    'lifestyle' => 'members',
                    'boundaries' => 'connections',
                    'photos' => 'members',
                ],
                'discoverable' => true,
                'message_permissions' => 'members',
                'show_age' => false,
                'show_last_active' => true,
            ]);
            NotificationPreference::create(['user_id' => $user->id]);

            $membershipStatus = null;
            if ($tenant) {
                $membershipStatus = $requiresMembershipApproval ? 'pending' : 'active';
                $tenant->users()->syncWithoutDetaching([
                    $user->id => ['role' => 'member', 'status' => $membershipStatus],
                ]);
                MembershipApplication::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    [
                        'status' => $requiresMembershipApproval ? 'pending' : 'approved',
                        'answers' => [
                            'lifestyle_identity' => $data['lifestyle_identity'],
                            'relationship_status' => $data['relationship_status'] ?? null,
                            'experience_level' => $data['experience_level'] ?? null,
                            'looking_for' => $data['looking_for'] ?? [],
                            'lifestyle_interests' => $data['lifestyle_interests'] ?? [],
                        ],
                        'member_note' => $data['application_note'] ?? null,
                        'reviewed_at' => $requiresMembershipApproval ? null : $now,
                    ]
                );
            }

            return [$user, $membershipStatus];
        });

        foreach (['adult_terms', 'privacy', 'community_conduct'] as $type) {
            ConsentRecord::create([
                'user_id' => $user->id,
                'consent_type' => $type,
                'document_version' => $type === 'privacy' ? config('platform.privacy_version', '1.0') : config('platform.terms_version', '1.0'),
                'granted' => true,
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
                'recorded_at' => $now,
            ]);
        }

        try { $user->sendEmailVerificationNotification(); } catch (\Throwable) {}
        Audit::write('auth.register', $user, request: $request);

        if ($requiresAccountApproval) {
            return redirect()->route('login')->with('status', 'Account created and awaiting administrator approval.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        if ($tenant && $membershipStatus === 'pending') {
            return redirect()->route('dashboard')->with('status', 'Your account is ready and your '.$tenant->name.' membership application is awaiting club approval.');
        }

        return redirect()->route('dashboard')->with('status', $tenant ? 'Welcome to '.$tenant->name.'. Your member profile is ready.' : 'Account created. Complete your lifestyle profile and privacy settings.');
    }

    public function logout(Request $request)
    {
        Audit::write('auth.logout', $request->user(), request: $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('site.home');
    }

    private function selfHostedTenant(): ?Tenant
    {
        $query = Tenant::query()->where('status', 'active');
        if ($tenantId = Edition::selfHostedTenantId()) {
            return $query->whereKey($tenantId)->first();
        }
        return $query->orderBy('id')->first();
    }
}
