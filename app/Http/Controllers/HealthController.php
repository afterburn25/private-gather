<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $healthy = true;

        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $healthy = false;
        }

        // Keep the unauthenticated probe deliberately minimal. Detailed
        // component/version information belongs in the authenticated system
        // health backend, not in a public fingerprinting endpoint.
        return response()->json(
            ['status' => $healthy ? 'ok' : 'degraded'],
            $healthy ? 200 : 503,
        );
    }
}
