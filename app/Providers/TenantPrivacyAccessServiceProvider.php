<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\PrivacyAccessController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TenantPrivacyAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/privacy-access', [PrivacyAccessController::class, 'edit'])->name('privacy-access.edit');
                Route::patch('/privacy-access', [PrivacyAccessController::class, 'update'])->name('privacy-access.update');
            });
    }
}
