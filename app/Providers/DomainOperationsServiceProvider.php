<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\DomainController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DomainOperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage/domains')
            ->name('tenant.domains.')
            ->group(function (): void {
                Route::post('/{domain}/diagnose', [DomainController::class, 'diagnose'])->whereNumber('domain')->name('diagnose');
                Route::post('/{domain}/provision', [DomainController::class, 'provision'])->whereNumber('domain')->name('provision');
                Route::post('/{domain}/ssl-check', [DomainController::class, 'sslCheck'])->whereNumber('domain')->name('ssl-check');
                Route::patch('/{domain}/redirect', [DomainController::class, 'redirect'])->whereNumber('domain')->name('redirect');
                Route::delete('/{domain}', [DomainController::class, 'destroy'])->whereNumber('domain')->name('destroy');
            });
    }
}
