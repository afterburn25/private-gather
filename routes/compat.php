<?php

use Illuminate\Support\Facades\Route;

/* Additive compatibility and feature routes loaded after the mature route map. */
if (! Route::has('clubs.index')) {
    Route::middleware('web')->redirect('/clubs', '/organizations', 302)->name('clubs.index');
}

if (! Route::has('member.badges.index')) {
    Route::middleware(['web', 'auth'])
        ->get('/badges', [\App\Http\Controllers\Member\BadgeController::class, 'index'])
        ->name('member.badges.index');
}

if (! Route::has('tenant.badges.index')) {
    Route::middleware(['web', 'auth', \App\Http\Middleware\EnsureTenantManager::class])
        ->prefix('manage')
        ->name('tenant.')
        ->group(function (): void {
            Route::get('/badges', [\App\Http\Controllers\Tenant\BadgeController::class, 'index'])->name('badges.index');
            Route::post('/badges', [\App\Http\Controllers\Tenant\BadgeController::class, 'store'])->name('badges.store');
            Route::patch('/badges/{badge}', [\App\Http\Controllers\Tenant\BadgeController::class, 'update'])->name('badges.update');
            Route::post('/badges/{badge}/assign', [\App\Http\Controllers\Tenant\BadgeController::class, 'assign'])->name('badges.assign');
            Route::delete('/badges/{badge}/assignments/{assignment}', [\App\Http\Controllers\Tenant\BadgeController::class, 'revoke'])->name('badges.revoke');
        });
}

if (! Route::has('admin.badges.index')) {
    Route::middleware(['web', \App\Http\Middleware\EnsurePlatformAdmin::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::get('/badges', [\App\Http\Controllers\Admin\BadgeController::class, 'index'])->name('badges.index');
            Route::post('/badges', [\App\Http\Controllers\Admin\BadgeController::class, 'store'])->name('badges.store');
            Route::patch('/badges/{badge}', [\App\Http\Controllers\Admin\BadgeController::class, 'update'])->name('badges.update');
            Route::post('/badges/{badge}/assign', [\App\Http\Controllers\Admin\BadgeController::class, 'assign'])->name('badges.assign');
            Route::delete('/badges/{badge}/assignments/{assignment}', [\App\Http\Controllers\Admin\BadgeController::class, 'revoke'])->name('badges.revoke');
        });
}
