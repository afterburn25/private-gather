<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\SecurityEvent;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SecurityController extends Controller
{
    private const SETUP_TTL_SECONDS = 600;

    public function index(Request $request)
    {
        return view('member.security', ['user' => $request->user()]);
    }

    public function beginTwoFactor(Request $request, TotpService $totp)
    {
        abort_if($request->user()->two_factor_confirmed_at, 422, 'Two-factor authentication is already enabled.');
        $request->validate(['password' => 'required|current_password']);

        $secret = $totp->secret();
        $request->session()->put([
            '2fa_setup_secret' => $secret,
            '2fa_setup_user' => $request->user()->id,
            '2fa_setup_issued_at' => now()->timestamp,
        ]);

        return view('member.two-factor', [
            'secret' => $secret,
            'uri' => $totp->uri($secret, $request->user()->email, config('app.name')),
        ]);
    }

    public function confirmTwoFactor(Request $request, TotpService $totp)
    {
        abort_if($request->user()->two_factor_confirmed_at, 422, 'Two-factor authentication is already enabled.');
        $data = $request->validate(['code' => 'required|string|max:30']);

        $secret = (string) $request->session()->get('2fa_setup_secret');
        $setupUser = $request->session()->get('2fa_setup_user');
        $issuedAt = $request->session()->get('2fa_setup_issued_at');
        $age = is_numeric($issuedAt) ? now()->timestamp - (int) $issuedAt : PHP_INT_MAX;
        $fresh = $secret !== ''
            && is_numeric($setupUser)
            && (int) $setupUser === (int) $request->user()->id
            && $age >= 0
            && $age <= self::SETUP_TTL_SECONDS;

        if (! $fresh) {
            $this->clearSetup($request);
            abort(422, 'Two-factor setup expired. Verify your password and begin setup again.');
        }

        abort_unless($totp->verify($secret, $data['code']), 422, 'Invalid authenticator code.');

        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->clearSetup($request);
        $request->session()->regenerate();
        $this->recordSecurityEvent($request, '2fa.enabled');

        return view('member.recovery-codes', ['codes' => $codes]);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        abort_unless($request->user()->two_factor_confirmed_at, 422, 'Two-factor authentication is not enabled.');

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->clearSetup($request);
        $request->session()->regenerate();
        $this->recordSecurityEvent($request, '2fa.disabled');

        return back()->with('status', 'Two-factor authentication disabled.');
    }

    public function dataRequest(Request $request)
    {
        $data = $request->validate(['type' => 'required|in:export,delete']);

        DataRequest::firstOrCreate(
            ['user_id' => $request->user()->id, 'type' => $data['type'], 'status' => 'pending'],
            ['requested_at' => now()]
        );

        return back()->with('status', 'Privacy request submitted.');
    }

    private function clearSetup(Request $request): void
    {
        $request->session()->forget([
            '2fa_setup_secret',
            '2fa_setup_user',
            '2fa_setup_issued_at',
        ]);
    }

    private function recordSecurityEvent(Request $request, string $event): void
    {
        SecurityEvent::create([
            'user_id' => $request->user()->id,
            'event' => $event,
            'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'occurred_at' => now(),
        ]);
    }
}
