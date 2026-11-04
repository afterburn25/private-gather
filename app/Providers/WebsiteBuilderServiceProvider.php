<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CmsFormController;
use App\Http\Controllers\Tenant\WebsiteBuilderController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WebsiteBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::post('/website/forms/{section}', [CmsFormController::class, 'store'])
                ->whereNumber('section')
                ->middleware('throttle:5,1')
                ->name('site.forms.store');

            Route::middleware(['auth', EnsureTenantManager::class])
                ->prefix('manage/website-builder')
                ->name('tenant.builder.')
                ->group(function (): void {
                    Route::get('/pages/{page}', [WebsiteBuilderController::class, 'edit'])->whereNumber('page')->name('pages.edit');
                    Route::patch('/pages/{page}', [WebsiteBuilderController::class, 'updatePage'])->whereNumber('page')->name('pages.update');
                    Route::post('/pages/{page}/sections', [WebsiteBuilderController::class, 'storeSection'])->whereNumber('page')->name('sections.store');
                    Route::post('/pages/{page}/reorder', [WebsiteBuilderController::class, 'reorder'])->whereNumber('page')->name('sections.reorder');
                    Route::get('/pages/{page}/preview', [WebsiteBuilderController::class, 'preview'])->whereNumber('page')->name('pages.preview');
                    Route::patch('/sections/{section}', [WebsiteBuilderController::class, 'updateSection'])->whereNumber('section')->name('sections.update');
                    Route::post('/sections/{section}/reusable', [WebsiteBuilderController::class, 'saveReusable'])->whereNumber('section')->name('reusable.store');
                    Route::post('/pages/{page}/reusable/{block}', [WebsiteBuilderController::class, 'insertReusable'])->whereNumber(['page', 'block'])->name('reusable.insert');
                    Route::delete('/reusable/{block}', [WebsiteBuilderController::class, 'archiveReusable'])->whereNumber('block')->name('reusable.archive');
                    Route::get('/submissions', [WebsiteBuilderController::class, 'submissions'])->name('submissions.index');
                    Route::patch('/submissions/{submission}', [WebsiteBuilderController::class, 'updateSubmission'])->whereNumber('submission')->name('submissions.update');
                });
        });
    }
}
