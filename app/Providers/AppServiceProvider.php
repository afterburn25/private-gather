<?php
namespace App\Providers;

use App\Contracts\DomainProvisioner;
use App\Contracts\PaymentGateway;
use App\Models\CmsNavigationItem;
use App\Models\SiteSetting;
use App\Services\ManualDomainProvisioner;
use App\Services\Payments\OfflinePaymentGateway;
use App\Services\PlatformContent;
use App\Support\ShowcaseBootstrap;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());
        $this->app->bind(DomainProvisioner::class, ManualDomainProvisioner::class);
        $this->app->bind(PaymentGateway::class, OfflinePaymentGateway::class);
    }

    public function boot(): void
    {
        ShowcaseBootstrap::runPending();

        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $relative = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false);

            return self::canonicalPlatformUrl($relative);
        });

        VerifyEmail::createUrlUsing(function ($notifiable): string {
            $relative = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                absolute: false,
            );

            return self::canonicalPlatformUrl($relative);
        });

        View::composer(['layouts.app', 'layouts.tenant-site'], function ($view): void {
            $tenant = app(TenantContext::class)->tenant();
            $settings = collect();
            $nav = collect();
            if ($tenant) {
                $tenant->loadMissing('branding');
                $settings = SiteSetting::where('tenant_id', $tenant->id)->where('is_public', true)->pluck('value', 'key');
                $nav = CmsNavigationItem::where('tenant_id', $tenant->id)->where('is_enabled', true)->orderBy('sort_order')->get()->groupBy('location');
            } else {
                $settings = collect(app(PlatformContent::class)->all());
            }
            $view->with([
                'layoutTenant' => $tenant,
                'siteSettings' => $settings,
                'tenantNavigation' => $nav,
            ]);
        });
    }

    private static function canonicalPlatformUrl(string $relative): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('APP_URL must be a canonical HTTP(S) URL before account email links can be generated.');
        }

        return $base.'/'.ltrim($relative, '/');
    }
}
