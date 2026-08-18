<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\RedirectResponse;

class TwoFactorChallengeController extends Controller
{
    private const CHALLENGE_TTL_SECONDS = 600;

    public function create(Request $request)
    {
        if (! $this->challengeIsFresh($request)) {
            return $this->expireChallenge($request);
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TotpService $totp)
    {
        if (! $this->challengeIsFresh($request)) {
            return $this->expireChallenge($request);
        }

        $data = $request->validate(['code' => 'required|string|max:30']);
        $id = $request->session()->get('auth.2fa_user');
        $user = User::where('status', 'active')->findOrFail($id);
        $valid = false;
        $recoveryUsed = false;

        try {
            $secret = Crypt::decryptString((string) $user->two_factor_secret);
            $valid = $totp->verify($secret, $data['code']);
        } catch (\Throwable) {
        }

        if (! $valid) {
            try {
                $codes = json_decode(Crypt::decryptString((string) $user->two_factor_recovery_codes), true) ?: [];
                $needle = strtolower(trim($data['code']));
                $index = array_search($needle, $codes, true);
                if ($index !== false) {
                    unset($codes[$index]);
                    $user->forceFill([
                        'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes))),
                    ])->save();
                    $valid = true;
                    $recoveryUsed = true;
                }
            } catch (\Throwable) {
            }
        }

        if (! $valid) {
            SecurityEvent::create([
                'user_id' => $user->id,
                'event' => '2fa.failed',
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                'occurred_at' => now(),
            ]);

            return back()->withErrors(['code' => 'The authentication code was not accepted.']);
        }

        $remember = (bool) $request->session()->pull('auth.2fa_remember', false);
        $intended = (string) $request->session()->pull('auth.2fa_intended', '/dashboard');
        $request->session()->forget(['auth.2fa_user', 'auth.2fa_issued_at']);
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        SecurityEvent::create([
            'user_id' => $user->id,
            'event' => $recoveryUsed ? '2fa.recovery_used' : '2fa.success',
            'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'occurred_at' => now(),
        ]);

        return redirect($intended);
    }

    private function challengeIsFresh(Request $request): bool
    {
        $userId = $request->session()->get('auth.2fa_user');
        $issuedAt = $request->session()->get('auth.2fa_issued_at');

        if (! is_numeric($userId) || ! is_numeric($issuedAt)) {
            return false;
        }

        $age = now()->timestamp - (int) $issuedAt;

        return $age >= 0 && $age <= self::CHALLENGE_TTL_SECONDS;
    }

    private function expireChallenge(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'auth.2fa_user',
            'auth.2fa_remember',
            'auth.2fa_intended',
            'auth.2fa_issued_at',
        ]);
        $request->session()->regenerate();

        return redirect()->route('login')->withErrors([
            'email' => 'Your two-factor authentication challenge expired. Please sign in again.',
        ]);
    }
}
