<?php

use Illuminate\Support\Facades\Route;

/* Additive compatibility and feature routes loaded after the mature route map. */
if (! Route::has('clubs.index')) {
    Route::middleware('web')->group(function (): void { Route::redirect('/clubs', '/organizations', 302)->name('clubs.index'); });
}

Route::middleware(['web','auth'])->group(function (): void {
    if (! Route::has('member.badges.index')) Route::get('/badges',[\App\Http\Controllers\Member\BadgeController::class,'index'])->name('member.badges.index');
    if (! Route::has('member.membership.show')) Route::get('/membership',[\App\Http\Controllers\Member\MembershipController::class,'show'])->name('member.membership.show');
});

if (! Route::has('tenant.badges.index')) {
    Route::middleware(['web','auth',\App\Http\Middleware\EnsureTenantManager::class])->prefix('manage')->name('tenant.')->group(function (): void {
        Route::get('/badges',[\App\Http\Controllers\Tenant\BadgeController::class,'index'])->name('badges.index');
        Route::post('/badges',[\App\Http\Controllers\Tenant\BadgeController::class,'store'])->name('badges.store');
        Route::patch('/badges/{badge}',[\App\Http\Controllers\Tenant\BadgeController::class,'update'])->name('badges.update');
        Route::post('/badges/{badge}/assign',[\App\Http\Controllers\Tenant\BadgeController::class,'assign'])->name('badges.assign');
        Route::delete('/badges/{badge}/assignments/{assignment}',[\App\Http\Controllers\Tenant\BadgeController::class,'revoke'])->name('badges.revoke');
    });
}

if (! Route::has('tenant.membership-levels.index')) {
    Route::middleware(['web','auth',\App\Http\Middleware\EnsureTenantManager::class])->prefix('manage')->name('tenant.')->group(function (): void {
        Route::get('/membership-levels',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'index'])->name('membership-levels.index');
        Route::post('/membership-levels',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'store'])->name('membership-levels.store');
        Route::patch('/membership-levels/{level}',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'update'])->name('membership-levels.update');
        Route::post('/membership-levels/{level}/default',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'makeDefault'])->name('membership-levels.default');
        Route::post('/memberships/assign',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'assign'])->name('memberships.assign');
        Route::post('/memberships/{term}/renew',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'renew'])->name('memberships.renew');
        Route::post('/memberships/{term}/cancel',[\App\Http\Controllers\Tenant\MembershipLevelController::class,'cancel'])->name('memberships.cancel');
    });
}

if (! Route::has('admin.badges.index')) {
    Route::middleware(['web',\App\Http\Middleware\EnsurePlatformAdmin::class])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/badges',[\App\Http\Controllers\Admin\BadgeController::class,'index'])->name('badges.index');Route::post('/badges',[\App\Http\Controllers\Admin\BadgeController::class,'store'])->name('badges.store');Route::patch('/badges/{badge}',[\App\Http\Controllers\Admin\BadgeController::class,'update'])->name('badges.update');Route::post('/badges/{badge}/assign',[\App\Http\Controllers\Admin\BadgeController::class,'assign'])->name('badges.assign');Route::delete('/badges/{badge}/assignments/{assignment}',[\App\Http\Controllers\Admin\BadgeController::class,'revoke'])->name('badges.revoke');
    });
}
