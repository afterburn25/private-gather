<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\AnnouncementController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TenantAnnouncementsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
                Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
                Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])
                    ->whereNumber('announcement')
                    ->name('announcements.publish');
            });
    }
}
