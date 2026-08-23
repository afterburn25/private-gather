<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\ProductInsightsController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Member\ProductExperienceController;
use App\Http\Controllers\Tenant\CommerceDashboardController;
use App\Http\Controllers\Tenant\ProductOperationsController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantManager;
use App\Support\Edition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ProductCompletionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            if (Edition::isHosted()) {
                Route::get('/discover', [MarketplaceController::class, 'discover'])->name('discover.index');
                Route::get('/clubs/{tenant:slug}', [MarketplaceController::class, 'club'])->name('clubs.show');
            }
            Route::get('/events/{event}/calendar.ics', [MarketplaceController::class, 'calendar'])->whereNumber('event')->name('events.calendar');
            Route::get('/r/{code}', [MarketplaceController::class, 'referral'])->name('referrals.capture');

            Route::middleware('auth')->group(function (): void {
                Route::get('/onboarding', [ProductExperienceController::class, 'onboarding'])->name('onboarding.index');
                Route::post('/onboarding', [ProductExperienceController::class, 'saveOnboarding'])->name('onboarding.save');
                Route::get('/saved', [ProductExperienceController::class, 'saved'])->name('saved.index');
                Route::post('/favorites/{type}/{id}', [ProductExperienceController::class, 'favorite'])->whereNumber('id')->name('favorites.store');
                Route::delete('/favorites/{type}/{id}', [ProductExperienceController::class, 'unfavorite'])->whereNumber('id')->name('favorites.destroy');
                Route::post('/saved-searches', [ProductExperienceController::class, 'saveSearch'])->name('saved-searches.store');
                Route::delete('/saved-searches/{id}', [ProductExperienceController::class, 'deleteSearch'])->whereNumber('id')->name('saved-searches.destroy');
                Route::get('/notifications', [ProductExperienceController::class, 'notifications'])->name('notifications.index');
                Route::patch('/notifications/preferences', [ProductExperienceController::class, 'notificationPreferences'])->name('notifications.preferences');
                Route::patch('/notifications/{id}', [ProductExperienceController::class, 'readNotification'])->name('notifications.read');
                Route::post('/notifications/read-all', [ProductExperienceController::class, 'readAllNotifications'])->name('notifications.read-all');
                Route::post('/notifications/push', [ProductExperienceController::class, 'pushSubscription'])->name('notifications.push.store');
                Route::delete('/notifications/push', [ProductExperienceController::class, 'removePushSubscription'])->name('notifications.push.destroy');
                Route::get('/privacy', [ProductExperienceController::class, 'privacy'])->name('privacy.index');
                Route::patch('/privacy', [ProductExperienceController::class, 'savePrivacy'])->name('privacy.update');
                Route::get('/connections', [ProductExperienceController::class, 'connections'])->name('connections.index');
                Route::get('/members/discover', [ProductExperienceController::class, 'memberDiscovery'])->name('members.discover');
                Route::post('/connections/{user}', [ProductExperienceController::class, 'requestConnection'])->name('connections.request');
                Route::patch('/connections/{id}', [ProductExperienceController::class, 'respondConnection'])->whereNumber('id')->name('connections.respond');
                Route::delete('/connections/{id}', [ProductExperienceController::class, 'removeConnection'])->whereNumber('id')->name('connections.destroy');
                Route::post('/follow/{type}/{id}', [ProductExperienceController::class, 'follow'])->whereNumber('id')->name('follows.store');
                Route::delete('/follow/{type}/{id}', [ProductExperienceController::class, 'unfollow'])->whereNumber('id')->name('follows.destroy');
                Route::post('/block/{user}', [ProductExperienceController::class, 'block'])->name('blocks.store');
                Route::delete('/block/{user}', [ProductExperienceController::class, 'unblock'])->name('blocks.destroy');
                Route::post('/reviews', [ProductExperienceController::class, 'review'])->name('reviews.store');
                Route::patch('/membership/subscriptions/{id}/cancel', [ProductExperienceController::class, 'cancelOwnSubscription'])->whereNumber('id')->name('membership.subscription.cancel');
            });

            Route::middleware(['auth', EnsureTenantManager::class])->prefix('manage')->name('tenant.')->group(function (): void {
                Route::get('/onboarding', [ProductOperationsController::class, 'onboarding'])->name('onboarding');
                Route::post('/onboarding', [ProductOperationsController::class, 'saveOnboarding'])->name('onboarding.save');
                Route::get('/crm', [ProductOperationsController::class, 'crm'])->name('crm.index');
                Route::post('/crm/tags', [ProductOperationsController::class, 'createTag'])->name('crm.tags.store');
                Route::post('/crm/members/{user}/tags', [ProductOperationsController::class, 'assignTag'])->name('crm.tags.assign');
                Route::post('/crm/members/{user}/notes', [ProductOperationsController::class, 'addNote'])->name('crm.notes.store');
                Route::get('/commerce', CommerceDashboardController::class)->name('commerce.index');
                Route::patch('/commerce/merchant', [ProductOperationsController::class, 'merchant'])->name('commerce.merchant');
                Route::post('/commerce/payouts', [ProductOperationsController::class, 'payout'])->name('commerce.payouts.store');
                Route::post('/commerce/orders/{order}/refund', [ProductOperationsController::class, 'refund'])->name('commerce.refunds.store');
                Route::post('/commerce/subscriptions', [ProductOperationsController::class, 'createSubscription'])->name('commerce.subscriptions.store');
                Route::patch('/commerce/subscriptions/{id}/cancel', [ProductOperationsController::class, 'cancelSubscription'])->whereNumber('id')->name('commerce.subscriptions.cancel');
                Route::patch('/commerce/subscriptions/{id}/renew', [ProductOperationsController::class, 'renewSubscription'])->whereNumber('id')->name('commerce.subscriptions.renew');
                Route::get('/domain-marketplace', [ProductOperationsController::class, 'domains'])->name('domain-marketplace.index');
                Route::get('/domain-marketplace/search', [ProductOperationsController::class, 'domainSearch'])->name('domain-marketplace.search');
                Route::post('/domain-marketplace/register', [ProductOperationsController::class, 'domainRegister'])->name('domain-marketplace.register');
                Route::get('/website-builder', [ProductOperationsController::class, 'builder'])->name('builder.index');
                Route::post('/website-builder/template', [ProductOperationsController::class, 'applyTemplate'])->name('builder.template');
                Route::get('/growth', [ProductOperationsController::class, 'growth'])->name('growth.index');
                Route::post('/growth/referrals', [ProductOperationsController::class, 'referral'])->name('growth.referrals.store');
                Route::post('/growth/contacts', [ProductOperationsController::class, 'contact'])->name('growth.contacts.store');
                Route::post('/growth/campaigns', [ProductOperationsController::class, 'campaign'])->name('growth.campaigns.store');
                Route::patch('/reviews/{id}/respond', [ProductOperationsController::class, 'respondReview'])->whereNumber('id')->name('reviews.respond');
            });

            Route::middleware(EnsurePlatformAdmin::class)->prefix('admin')->name('admin.')->group(function (): void {
                Route::get('/insights', [ProductInsightsController::class, 'analytics'])->name('insights.index');
                Route::get('/trust-safety', [ProductInsightsController::class, 'trust'])->name('trust.index');
                Route::patch('/trust-safety/{id}', [ProductInsightsController::class, 'updateTrust'])->whereNumber('id')->name('trust.update');
            });
        });
    }
}
