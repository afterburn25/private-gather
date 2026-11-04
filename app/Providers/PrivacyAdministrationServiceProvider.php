<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\PrivacyRequestController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PrivacyAdministrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', EnsurePlatformAdmin::class])
            ->prefix('admin/privacy-requests')
            ->name('admin.privacy-requests.')
            ->group(function (): void {
                Route::get('/', [PrivacyRequestController::class, 'index'])->name('index');
                Route::patch('/{privacyRequest}', [PrivacyRequestController::class, 'update'])
                    ->whereNumber('privacyRequest')
                    ->name('update');
            });
    }
}
