<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\EventWaitlistController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class EventWaitlistServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage/events/{event}/waitlist')
            ->whereNumber('event')
            ->group(function (): void {
                Route::get('/', [EventWaitlistController::class, 'index'])
                    ->name('tenant.events.waitlist.index');
                Route::patch('/{waitlist}/promote', [EventWaitlistController::class, 'promote'])
                    ->whereNumber('waitlist')
                    ->name('tenant.events.waitlist.promote');
            });
    }
}
