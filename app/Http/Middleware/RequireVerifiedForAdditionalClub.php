<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Verification\MemberTrust;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireVerifiedForAdditionalClub
{
    public function __construct(private readonly MemberTrust $trust) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $tenant = $request->route('tenant');
        if ($tenant instanceof Tenant) {
            $existing = $user->tenants()->whereKey($tenant->id)->first()?->pivot;
            if ($existing && in_array($existing->status, ['active', 'pending'], true)) {
                return $next($request);
            }
        }

        if ($this->trust->canJoinAnotherClub($user)) {
            return $next($request);
        }

        $message = $this->trust->badge($user)['required_count'] === 2
            ? 'Both linked partners must complete ID and live-selfie verification before this couple profile can join another club.'
            : 'Complete ID and live-selfie verification before joining another club.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'verification_url' => route('verification.index')], 403);
        }

        return redirect()->route('verification.index')->with('status', $message);
    }
}
