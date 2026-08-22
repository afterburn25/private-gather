<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\GrowthCommerceController;
use App\Http\Controllers\Tenant\MembershipManagementController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GrowthCommerceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage/growth')
            ->group(function (): void {
                Route::get('/', [GrowthCommerceController::class, 'index'])->name('tenant.growth.index');
                Route::post('/membership-levels', [GrowthCommerceController::class, 'storeMembership'])->name('tenant.growth.memberships.store');
                Route::patch('/membership-levels/{membershipLevel}', [GrowthCommerceController::class, 'updateMembership'])->whereNumber('membershipLevel')->name('tenant.growth.memberships.update');
                Route::get('/members', [MembershipManagementController::class, 'index'])->name('tenant.growth.members.index');
                Route::patch('/members/{user}', [MembershipManagementController::class, 'update'])->whereNumber('user')->name('tenant.growth.members.update');
                Route::post('/promoters', [GrowthCommerceController::class, 'storePromoter'])->name('tenant.growth.promoters.store');
                Route::patch('/promoters/{promoter}', [GrowthCommerceController::class, 'updatePromoter'])->whereNumber('promoter')->name('tenant.growth.promoters.update');
                Route::post('/contacts', [GrowthCommerceController::class, 'storeContact'])->name('tenant.growth.contacts.store');
                Route::post('/lists', [GrowthCommerceController::class, 'storeList'])->name('tenant.growth.lists.store');
                Route::post('/campaigns', [GrowthCommerceController::class, 'storeCampaign'])->name('tenant.growth.campaigns.store');
            });

        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage/events/{event}/addons')
            ->whereNumber('event')
            ->group(function (): void {
                Route::post('/', [GrowthCommerceController::class, 'storeAddon'])->name('tenant.growth.addons.store');
                Route::delete('/{addon}', [GrowthCommerceController::class, 'destroyAddon'])->whereNumber('addon')->name('tenant.growth.addons.destroy');
            });
    }
}
