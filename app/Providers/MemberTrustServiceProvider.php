<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AgeVerificationProvider;
use App\Http\Controllers\ClubMembershipController;
use App\Http\Controllers\Member\AgeVerificationController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\RequireVerifiedForAdditionalClub;
use App\Services\Verification\MemberTrust;
use App\Services\Verification\PersonaAgeVerificationProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MemberTrustServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MemberTrust::class);
        $this->app->bind(AgeVerificationProvider::class, function () {
            return match ((string) config('age_verification.driver', 'persona')) {
                'persona' => app(PersonaAgeVerificationProvider::class),
                default => throw new \RuntimeException('Unsupported age verification driver.'),
            };
        });
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/verification', [AgeVerificationController::class, 'index'])->name('verification.index');
            Route::post('/verification/start/{slot}', [AgeVerificationController::class, 'start'])
                ->where('slot', 'primary|partner')->middleware('throttle:8,1')->name('verification.start');
            Route::get('/verification/return', [AgeVerificationController::class, 'returned'])->name('verification.return');

            Route::post('/clubs/{tenant:slug}/apply', [ClubMembershipController::class, 'apply'])
                ->middleware([RequireVerifiedForAdditionalClub::class, 'throttle:10,1'])
                ->name('clubs.apply');
        });

        // Persona cannot supply Laravel CSRF. Authenticity is enforced by raw-body
        // Persona-Signature HMAC verification plus event-id replay protection.
        Route::post('/verification/webhooks/persona', [AgeVerificationController::class, 'webhook'])
            ->middleware('throttle:120,1')
            ->name('verification.webhooks.persona');

        Route::middleware(['web', EnsurePlatformAdmin::class])->group(function (): void {
            Route::get('/admin/age-verification', [AgeVerificationController::class, 'admin'])->name('admin.age-verification.index');
        });
    }
}
