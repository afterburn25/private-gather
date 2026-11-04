<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\ProductInsightsController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TrustOperationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web',EnsurePlatformAdmin::class])->prefix('admin/trust-safety')->name('admin.trust.')->group(function (): void {
            Route::post('/reports/{id}/escalate',[ProductInsightsController::class,'escalateReport'])->whereNumber('id')->name('report.escalate');
            Route::post('/verifications/{verification}/escalate',[ProductInsightsController::class,'escalateVerification'])->name('verification.escalate');
        });
    }
}
