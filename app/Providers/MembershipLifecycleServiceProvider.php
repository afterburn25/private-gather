<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\MemberOperationsController;
use App\Http\Controllers\Tenant\MembershipInvitationController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MembershipLifecycleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/membership-invite/{token}', [MembershipInvitationController::class, 'showAccept'])
                ->where('token', '[A-Za-z0-9]{64}')
                ->middleware('throttle:30,1')
                ->name('membership-invitations.accept.show');
            Route::post('/membership-invite/{token}', [MembershipInvitationController::class, 'accept'])
                ->where('token', '[A-Za-z0-9]{64}')
                ->middleware('throttle:10,1')
                ->name('membership-invitations.accept');
        });

        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/membership-invitations', [MembershipInvitationController::class, 'index'])
                    ->name('membership-invitations.index');
                Route::post('/membership-invitations', [MembershipInvitationController::class, 'store'])
                    ->middleware('throttle:20,1')
                    ->name('membership-invitations.store');
                Route::delete('/membership-invitations/{invitation}', [MembershipInvitationController::class, 'revoke'])
                    ->whereNumber('invitation')
                    ->name('membership-invitations.revoke');

                Route::get('/member-operations', [MemberOperationsController::class, 'index'])
                    ->name('member-operations.index');
                Route::post('/member-operations/bulk', [MemberOperationsController::class, 'bulk'])
                    ->middleware('throttle:20,1')
                    ->name('member-operations.bulk');
                Route::post('/member-operations/import', [MemberOperationsController::class, 'import'])
                    ->middleware('throttle:10,1')
                    ->name('member-operations.import');
                Route::get('/member-operations/export', [MemberOperationsController::class, 'export'])
                    ->middleware('throttle:10,1')
                    ->name('member-operations.export');
            });
    }
}
