<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\AffiliateOfferController;
use App\Http\Controllers\HostedDiscoveryController;
use App\Http\Controllers\Tenant\ClubDirectoryController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantManager;
use App\Support\Edition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class HostedDiscoveryRevenueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! Edition::isHosted()) {
            return;
        }

        Route::middleware('web')->group(function (): void {
            Route::get('/clubs', [HostedDiscoveryController::class, 'clubs'])->name('clubs.index');
            Route::get('/clubs/{slug}', [HostedDiscoveryController::class, 'club'])
                ->where('slug', '[A-Za-z0-9_-]+')
                ->name('clubs.show');
            Route::get('/go/{offer}', [HostedDiscoveryController::class, 'affiliate'])
                ->whereNumber('offer')
                ->middleware('throttle:120,1')
                ->name('affiliate.go');
        });

        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->group(function (): void {
                Route::get('/club-directory', [ClubDirectoryController::class, 'edit'])->name('tenant.club-directory.edit');
                Route::patch('/club-directory', [ClubDirectoryController::class, 'update'])->name('tenant.club-directory.update');
            });

        Route::middleware(['web', EnsurePlatformAdmin::class])
            ->prefix('admin/affiliate-offers')
            ->group(function (): void {
                Route::get('/', [AffiliateOfferController::class, 'index'])->name('admin.affiliate-offers.index');
                Route::post('/', [AffiliateOfferController::class, 'store'])->name('admin.affiliate-offers.store');
                Route::patch('/{offer}', [AffiliateOfferController::class, 'update'])->whereNumber('offer')->name('admin.affiliate-offers.update');
                Route::post('/{offer}/conversions', [AffiliateOfferController::class, 'conversion'])->whereNumber('offer')->name('admin.affiliate-offers.conversions.store');
            });
    }
}
