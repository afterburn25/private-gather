<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Tenant\OrganizationController;
use App\Support\Edition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class HostedSubdomainProvisioningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! Edition::isHosted()) {
            return;
        }

        Route::middleware(['web', 'auth'])
            ->post('/my-organizations/{tenant}/provision-subdomain', [OrganizationController::class, 'provision'])
            ->whereNumber('tenant')
            ->name('organizations.provision-subdomain');
    }
}
