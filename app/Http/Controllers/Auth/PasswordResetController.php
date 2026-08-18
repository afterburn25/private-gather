<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email|max:190']);
        $email = strtolower(trim((string) $validated['email']));

        Password::sendResetLink(['email' => $email]);

        return back()->with('status', 'If an account exists for that email address, a password reset link has been sent.');
    }

    public function resetForm(Request $request, string $token)
    {
        abort_if(strlen($token) > 255, 404);

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:255',
            'email' => 'required|email|max:190',
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $data['email'] = strtolower(trim((string) $data['email']));

        $status = Password::reset($data, function (User $user, string $password) use ($request): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            // Private Gather uses database-backed sessions in production. A
            // password reset is a credential-recovery event, so invalidate any
            // existing authenticated sessions for the account as well.
            DB::table('sessions')->where('user_id', $user->id)->delete();

            SecurityEvent::create([
                'user_id' => $user->id,
                'event' => 'password.reset',
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                'occurred_at' => now(),
            ]);

            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
