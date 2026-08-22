<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\OfflinePaymentGateway;
use App\Support\DemoMode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class DemoEnvironmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! DemoMode::enabled()) {
            return;
        }

        if (! DemoMode::allows('mail')) {
            $this->app['config']->set('mail.default', 'log');
        }

        if (! DemoMode::allows('payments')) {
            $this->app->bind(PaymentGateway::class, OfflinePaymentGateway::class);
        }
    }

    public function boot(): void
    {
        View::share('privateGatherDemo', DemoMode::enabled());

        Route::middleware('web')->get('/demo-guide', function () {
            abort_unless(DemoMode::enabled() && hash_equals(DemoMode::host(), strtolower(request()->getHost())), 404);

            return view('demo.guide', [
                'accounts' => (array) config('demo.accounts', []),
                'demoPassword' => (string) config('demo.password'),
                'tenantHost' => 'horizon.'.(string) config('platform.root_domain'),
            ]);
        })->name('demo.guide');
    }
}
