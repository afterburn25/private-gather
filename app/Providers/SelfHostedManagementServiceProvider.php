<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\MemberManageController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SelfHostedManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/members', [MemberManageController::class, 'index'])->name('members.index');
                Route::patch('/members/{user}', [MemberManageController::class, 'update'])->name('members.update');
            });
    }
}
