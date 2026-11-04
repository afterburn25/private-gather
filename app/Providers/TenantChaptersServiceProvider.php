<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\ChapterController;
use App\Http\Controllers\Tenant\ChapterOperationsController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TenantChaptersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/chapters/{slug}', [ChapterOperationsController::class, 'show'])
                ->where('slug', '[A-Za-z0-9-]+')
                ->name('chapters.show');

            Route::middleware('auth')->prefix('manage')->name('tenant.')->group(function (): void {
                Route::get('/chapters/{chapter}/workspace', [ChapterOperationsController::class, 'workspace'])->whereNumber('chapter')->name('chapters.workspace');
                Route::patch('/chapters/{chapter}/branding', [ChapterOperationsController::class, 'updateBranding'])->whereNumber('chapter')->name('chapters.branding');
                Route::post('/chapters/{chapter}/events', [ChapterOperationsController::class, 'storeEvent'])->whereNumber('chapter')->name('chapters.events.store');
                Route::post('/chapters/{chapter}/announcements', [ChapterOperationsController::class, 'storeAnnouncement'])->whereNumber('chapter')->name('chapters.announcements.store');
            });
        });

        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/chapters', [ChapterController::class, 'index'])->name('chapters.index');
                Route::post('/chapters', [ChapterController::class, 'store'])->name('chapters.store');
                Route::patch('/chapters/{chapter}', [ChapterController::class, 'update'])->whereNumber('chapter')->name('chapters.update');
                Route::patch('/chapters/{chapter}/archive', [ChapterController::class, 'archive'])->whereNumber('chapter')->name('chapters.archive');
                Route::patch('/chapters/{chapter}/restore', [ChapterController::class, 'restore'])->whereNumber('chapter')->name('chapters.restore');
            });
    }
}
