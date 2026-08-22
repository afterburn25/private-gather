<?php

use App\Http\Middleware\DemoEnvironmentHeaders;
use App\Http\Middleware\EnforceSelfHostedPrivacy;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\ResolveTenantByDomain;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepend so the browser-security boundary wraps tenant/account
        // middleware and every successful web response produced beneath it.
        $middleware->web(
            prepend: [SecurityHeaders::class, DemoEnvironmentHeaders::class],
            append: [
                ResolveTenantByDomain::class,
                EnforceSelfHostedPrivacy::class,
                EnsureActiveAccount::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exception reporting customization will be added as the platform grows.
    })->create();
