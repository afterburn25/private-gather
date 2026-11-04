<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Edition;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(TenantContext $context): View
    {
        // Hosted Edition deliberately hides the platform-admin login from tenant
        // domains. Self-Hosted always has a tenant context, so its local admin
        // login must remain reachable on that one customer-owned site.
        abort_if(Edition::isHosted() && $context->check(), 404);

        return view('admin.auth.login');
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        abort_if(Edition::isHosted() && $context->check(), 404);
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(array_merge($credentials, ['status' => 'active']), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The administrator credentials were not accepted.'])->onlyInput('email');
        }

        $user = $request->user();
        if (! $user?->is_platform_admin) {
            Auth::logout();

            return back()->withErrors(['email' => Edition::isSelfHosted()
                ? 'This account does not have local administration access.'
                : 'This account does not have platform administration access.']);
        }

        $request->session()->regenerate();

        if ($user->two_factor_confirmed_at) {
            $request->session()->put([
                'auth.2fa_user' => $user->id,
                'auth.2fa_remember' => $request->boolean('remember'),
                'auth.2fa_intended' => '/admin',
                'auth.2fa_issued_at' => now()->timestamp,
            ]);
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended(route('admin.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
