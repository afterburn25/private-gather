<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Edition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class MemberAuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt([
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => 'active',
        ], $request->boolean('remember'))) {
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

    public function registerForm()
    {
        abort_unless(Edition::registrationEnabled(), 404);

        return view('auth.register');
    }

    public function register(Request $request)
    {
        abort_unless(Edition::registrationEnabled(), 404);

        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'display_name' => 'required|string|max:80',
            'email' => 'required|email|max:190|unique:users,email',
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'adult' => 'accepted',
            'terms' => 'accepted',
            'privacy' => 'accepted',
        ]);
        $now = now();
        $requiresApproval = Edition::isSelfHosted() && Edition::selfHostedRegistration() === 'approval';
        $user = User::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'email' => $data['email'],
            'date_of_birth' => $data['date_of_birth'],
            'password' => $data['password'],
            'status' => $requiresApproval ? 'pending' : 'active',
            'adult_confirmed_at' => $now,
            'terms_accepted_at' => $now,
            'privacy_accepted_at' => $now,
            'privacy_version' => config('platform.privacy_version', '1.0'),
        ]);
        Profile::create([
            'user_id' => $user->id,
            'profile_type' => 'individual',
            'visibility' => ['bio' => 'members', 'photos' => 'members'],
            'discoverable' => true,
        ]);
        foreach (['adult_terms', 'privacy'] as $type) {
            ConsentRecord::create([
                'user_id' => $user->id,
                'consent_type' => $type,
                'document_version' => $type === 'privacy' ? config('platform.privacy_version', '1.0') : config('platform.terms_version', '1.0'),
                'granted' => true,
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
                'recorded_at' => $now,
            ]);
        }

        if (Edition::isSelfHosted()) {
            $tenant = $this->selfHostedTenant();
            if ($tenant) {
                $tenant->users()->syncWithoutDetaching([
                    $user->id => ['role' => 'member', 'status' => 'active'],
                ]);
            }
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
        }
        Audit::write('auth.register', $user, request: $request);

        if ($requiresApproval) {
            return redirect()->route('login')->with('status', 'Account created and awaiting administrator approval.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Account created. Check your email for a verification link if email delivery is configured.');
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
