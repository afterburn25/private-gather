<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        return view('member.security', ['user' => $request->user()]);
    }

    public function beginTwoFactor(Request $request, TotpService $totp)
    {
        abort_if($request->user()->two_factor_confirmed_at, 422, 'Two-factor authentication is already enabled.');
        $request->validate(['password' => 'required|current_password']);

        $secret = $totp->secret();
        $request->session()->put('2fa_setup_secret', $secret);

        return view('member.two-factor', [
            'secret' => $secret,
            'uri' => $totp->uri($secret, $request->user()->email, config('app.name')),
        ]);
    }

    public function confirmTwoFactor(Request $request, TotpService $totp)
    {
        abort_if($request->user()->two_factor_confirmed_at, 422, 'Two-factor authentication is already enabled.');
        $data = $request->validate(['code' => 'required|string']);
        $secret = (string) $request->session()->get('2fa_setup_secret');
        abort_unless($secret && $totp->verify($secret, $data['code']), 422, 'Invalid authenticator code.');

        $codes = $totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('2fa_setup_secret');

        return view('member.recovery-codes', ['codes' => $codes]);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $request->session()->forget('2fa_setup_secret');

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
}
