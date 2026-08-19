<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Member\SecurityController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AccountSecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])
            ->post('/security/sessions/revoke', [SecurityController::class, 'revokeOtherSessions'])
            ->name('member.security.sessions.revoke');
    }
}
