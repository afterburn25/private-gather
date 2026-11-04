<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CommunicationsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CommunicationUnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $token, CommunicationsService $communications): View
    {
        try {
            $tenantName = $communications->unsubscribe($token);
            return view('communications.unsubscribe', ['success' => true, 'tenantName' => $tenantName]);
        } catch (\Throwable $exception) {
            report($exception);
            return view('communications.unsubscribe', ['success' => false, 'tenantName' => null]);
        }
    }
}
