<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\CommunicationUnsubscribeController;
use App\Http\Controllers\Tenant\CommunicationsController;
use App\Http\Middleware\EnsureTenantManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CommunicationsCenterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/communications/unsubscribe/{token}', CommunicationUnsubscribeController::class)
                ->where('token', '[A-Za-z0-9]{64}')
                ->middleware('throttle:60,1')
                ->name('communications.unsubscribe');
        });

        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/communications', [CommunicationsController::class, 'index'])->name('communications.index');
                Route::post('/communications/segments', [CommunicationsController::class, 'storeSegment'])->name('communications.segments.store');
                Route::post('/communications/templates', [CommunicationsController::class, 'storeTemplate'])->name('communications.templates.store');
                Route::post('/communications/campaigns', [CommunicationsController::class, 'storeCampaign'])->name('communications.campaigns.store');
                Route::post('/communications/campaigns/{campaign}/queue', [CommunicationsController::class, 'queueCampaign'])
                    ->whereNumber('campaign')
                    ->name('communications.campaigns.queue');
            });
    }
}
