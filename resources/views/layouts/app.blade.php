@php
    $tenant = $layoutTenant ?? app(\App\Tenancy\TenantContext::class)->tenant();
    $branding = $tenant?->branding;
    $primary = ($branding?->primary_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding->primary_color))
        ? $branding->primary_color
        : '#8b5cf6';
    $accent = ($branding?->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding->accent_color))
        ? $branding->accent_color
        : '#6d28d9';
    $isAdmin = request()->routeIs('admin.*') && ! request()->routeIs('admin.login*');
    $isPlatformAdmin = auth()->check() && (bool) auth()->user()->is_platform_admin;
    $isHosted = \App\Support\Edition::isHosted();
    $isDemo = \App\Support\DemoMode::enabled();
    $canRegister = \App\Support\Edition::registrationEnabled();
    $canManage = false;
    $isCommunityMember = false;
    $releaseVersion = (string) config('release.version', '0.0.0');

    if (auth()->check() && $tenant) {
        $membership = auth()->user()->tenants()->whereKey($tenant->id)->first()?->pivot;
        $isCommunityMember = $membership && $membership->status === 'active';
        $canManage = $isCommunityMember && (
            $isPlatformAdmin
            || in_array($membership->role, ['owner', 'admin', 'manager', 'staff', 'checkin'], true)
        );
    }

    $headerNav = $tenant ? ($tenantNavigation['header'] ?? collect()) : collect();
    $footerNav = $tenant ? ($tenantNavigation['footer'] ?? collect()) : collect();
    $platformName = $siteSettings['brand_name'] ?? config('app.name', 'Private Gather');
    $platformLogo = \App\Support\MountUrl::to('/assets/branding/private-gather-crest-gold.webp');
@endphp
<!doctype html>
<html lang="en" data-pg-release="{{ $releaseVersion }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="private-gather-release" content="{{ $releaseVersion }}">
    <title>@yield('title', $tenant?->name ?? $platformName)</title>
    <meta name="description" content="@yield('meta_description', 'Private Gather connects consenting adults with private social events, clubs and trusted organizers through privacy-focused RSVP and ticketing.')">

    @if ($tenant && $branding?->favicon_path)
        <link rel="icon" href="{{ \App\Support\MountUrl::to($branding->favicon_path) }}">
    @elseif (! $tenant)
        <link rel="icon" type="image/webp" href="{{ $platformLogo }}">
    @endif

    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/app.css') }}?v={{ rawurlencode($releaseVersion) }}">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-1.1.css') }}?v={{ rawurlencode($releaseVersion) }}">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-public-refresh.css') }}?v={{ rawurlencode($releaseVersion) }}">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-luxury-2026.css') }}?v={{ rawurlencode($releaseVersion) }}-r12">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/private-gather-community-1.2.css') }}?v={{ rawurlencode($releaseVersion) }}">
    <link rel="manifest" href="{{ \App\Support\MountUrl::to('/manifest.webmanifest') }}?v={{ rawurlencode($releaseVersion) }}">
    <style>
        :root {
            --gold: {{ $primary }};
            --gold2: {{ $primary }};
            --plum: {{ $accent }};
        }
        @if ($branding?->font_family)
            body { font-family: {{ json_encode($branding->font_family) }}, sans-serif; }
        @endif
    </style>
</head>
<body class="{{ $isAdmin ? 'admin-body' : 'pg-public-page pg-luxury-theme' }} {{ $isDemo ? 'demo-mode' : '' }}" data-pg-release="{{ $releaseVersion }}">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ \App\Support\MountUrl::to('/') }}">
            @if ($tenant)
                @if ($branding?->logo_path)
                    <img class="brand-logo" src="{{ \App\Support\MountUrl::to($branding->logo_path) }}" alt="{{ $tenant->name }} logo">
                @else
                    <span class="brand-mark">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                @endif
                <span>{{ $tenant->name }}</span>
            @else
                <img class="brand-logo platform-brand-logo" src="{{ $platformLogo }}" alt="{{ $platformName }} logo">
                <span>{{ $platformName }}</span>
            @endif
        </a>

        @if ($tenant && $headerNav->isNotEmpty())
            <nav class="nav-links" aria-label="Primary">
                @foreach ($headerNav as $item)
                    <a href="{{ \App\Support\MountUrl::to($item->url) }}">{{ $item->label }}</a>
                @endforeach
                @if ($isCommunityMember)
                    <a href="{{ route('community.index') }}">Community</a>
                    <a href="{{ route('network.index') }}">Members</a>
                    <a href="{{ route('messages.index') }}">Messages</a>
                    <a href="{{ route('notifications.index') }}">Alerts</a>
                @endif
            </nav>
        @elseif ($tenant)
            <nav class="nav-links" aria-label="Primary">
                <a href="{{ route('site.events') }}">Events</a>
                <a href="{{ route('site.about') }}">About</a>
                @if ($isCommunityMember)
                    <a href="{{ route('community.index') }}">Community</a>
                    <a href="{{ route('network.index') }}">Members</a>
                    <a href="{{ route('messages.index') }}">Messages</a>
                    <a href="{{ route('notifications.index') }}">Alerts</a>
                @endif
            </nav>
        @else
            <nav class="nav-links pg-public-nav" aria-label="Primary">
                <a class="{{ request()->routeIs('site.home') ? 'is-active' : '' }}" href="{{ route('site.home') }}">Home</a>
                <a class="{{ request()->routeIs('site.events') || request()->routeIs('events.show') ? 'is-active' : '' }}" href="{{ route('site.events') }}">Events</a>
                @if ($isHosted)<a class="{{ request()->routeIs('clubs.*') ? 'is-active' : '' }}" href="{{ route('clubs.index') }}">Communities</a>@endif
                <a href="{{ route('site.home') }}#membership">Memberships</a>
                @if ($isHosted)<a class="{{ request()->routeIs('site.organizations') ? 'is-active' : '' }}" href="{{ route('site.organizations') }}">Venues</a>@endif
            </nav>
        @endif

        <div class="header-actions">
            @if (auth()->check())
                @if ($isPlatformAdmin)
                    <a class="button button-ghost" href="{{ route('admin.home') }}">Admin</a>
                @endif

                @if ($canManage)
                    <a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Manage</a>
                @elseif (! $tenant && $isHosted)
                    <a class="button button-ghost" href="{{ route('organizations.index') }}">My Clubs</a>
                @endif

                <a class="button button-primary" href="{{ route('dashboard') }}">Account</a>
            @else
                <a class="button button-ghost" href="{{ route('login') }}">Sign In</a>

                @if ($canRegister)
                    <a class="button button-primary" href="{{ \App\Support\MountUrl::to($siteSettings['header_cta_url'] ?? route('register')) }}">
                        Join Now
                    </a>
                @endif
            @endif
        </div>

        <details class="pg-mobile-nav">
            <summary>Menu</summary>
            <nav class="pg-mobile-nav-menu" aria-label="Mobile navigation">
                <a href="{{ route('site.home') }}">Home</a>
                <a href="{{ route('site.events') }}">Events</a>
                @if($tenant)
                    <a href="{{ route('club.news.index') }}">News</a>
                    @if($isCommunityMember)
                        <a href="{{ route('community.index') }}">Club Wall</a>
                        <a href="{{ route('network.index') }}">Members & Galleries</a>
                        <a href="{{ route('messages.index') }}">Messages</a>
                        <a href="{{ route('notifications.index') }}">Notifications</a>
                        <a href="{{ route('community.chat') }}">Live Chat</a>
                    @endif
                @else
                    @if ($isHosted)<a href="{{ route('clubs.index') }}">Communities</a>@endif
                    <a href="{{ route('site.home') }}#membership">Memberships</a>
                    @if ($isHosted)<a href="{{ route('site.organizations') }}">Venues</a>@endif
                @endif
                @auth<a href="{{ route('dashboard') }}">My Account</a>@else<a href="{{ route('login') }}">Sign In</a>@endauth
            </nav>
        </details>
    </div>
</header>

@if ($isDemo)
    <div class="pg-demo-banner" role="status">
        <div class="container"><span class="pg-demo-dot"></span> Private Gather Demo Sandbox · sample data · no live charges</div>
    </div>
@endif

@if ($isAdmin)
    <nav class="admin-nav">
        <div class="container">
            <a href="{{ route('admin.home') }}">Private Gather</a>
            <a href="{{ route('admin.users.index') }}">Users</a>

            @if ($isHosted)
                <a href="{{ route('admin.tenants.index') }}">Organizations</a>
                <a href="{{ route('admin.plans.index') }}">Plans</a>
                <a href="{{ route('admin.content.edit') }}">Website</a>
                <a href="{{ route('admin.affiliate-offers.index') }}">Affiliate Revenue</a>
            @endif

            <a href="{{ route('admin.moderation.index') }}">Moderation</a>
            <a href="{{ route('admin.upgrades.index') }}">Upgrades</a>
            <a href="{{ route('admin.health.index') }}">Health</a>
        </div>
    </nav>
@endif

<main>
    @if (session('status'))
        <div class="container flash">
            <div class="notice">{{ session('status') }}</div>
        </div>
    @endif

    @yield('content')
</main>

@if (! $tenant && ! $isAdmin)
    @include('partials.ad-leaderboard',[
        'adSlot'=>'global_footer_leaderboard',
        'adOffer'=>isset($affiliateOffers) ? $affiliateOffers->get(2) : null,
    ])
@endif

<footer class="site-footer">
    @if (! $tenant)
        <div class="container footer-grid pg-footer-grid">
            <div>
                <div class="brand footer-brand">
                    <img class="brand-logo platform-brand-logo footer-platform-logo" src="{{ $platformLogo }}" alt="{{ $platformName }} logo">
                    <span>{{ $platformName }}</span>
                </div>
                <p class="muted pg-footer-note">Curated adults-only experiences. Private communities. Real connections—with privacy and discretion built in.</p>
            </div>
            <div>
                <strong class="pg-footer-heading">Company</strong>
                <a href="{{ route('site.about') }}">About Us</a>
                <a href="{{ route('site.organizations') }}">Our Story</a>
                <a href="{{ route('site.organizations') }}">For Organizers</a>
                <a href="{{ route('site.about') }}">Privacy</a>
            </div>
            <div>
                <strong class="pg-footer-heading">Membership</strong>
                @if ($canRegister)<a href="{{ route('register') }}">Join Private Gather</a>@endif
                <a href="{{ route('site.home') }}#membership">Benefits</a>
                <a href="{{ route('site.events') }}">Event Access</a>
                <a href="{{ route('login') }}">Member Login</a>
            </div>
            <div>
                <strong class="pg-footer-heading">Resources</strong>
                <a href="{{ route('site.events') }}">Event Calendar</a>
                @if ($isHosted)<a href="{{ route('clubs.index') }}">Communities</a>@endif
                @if ($isHosted)<a href="{{ route('site.organizations') }}">Venues</a>@endif
                <a href="{{ route('site.about') }}">Safety & Consent</a>
            </div>
        </div>
        <div class="container powered">© {{ now()->year }} Private Gather · privategather.com · Discretion. Quality. Connection.</div>
    @else
        <div class="container footer-grid">
            <div>
                <div class="brand footer-brand">
                    @if ($branding?->logo_path)
                        <img class="brand-logo" src="{{ \App\Support\MountUrl::to($branding->logo_path) }}" alt="{{ $tenant->name }} logo">
                    @else
                        <span class="brand-mark">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                    @endif
                    <span>{{ $tenant->name }}</span>
                </div>
                <p class="muted">{{ $siteSettings['footer_text'] ?? $siteSettings['tagline'] ?? config('brand.tagline', 'Discretion. Quality. Connection.') }}</p>
            </div>
            <div>
                <strong>Explore</strong>
                @if ($footerNav->isNotEmpty())
                    @foreach ($footerNav as $item)<a href="{{ \App\Support\MountUrl::to($item->url) }}">{{ $item->label }}</a>@endforeach
                @else
                    <a href="{{ route('site.events') }}">Events</a>
                    <a href="{{ route('site.about') }}">About</a>
                @endif
                @if ($isCommunityMember)<a href="{{ route('community.index') }}">Community</a><a href="{{ route('network.index') }}">Members & Galleries</a><a href="{{ route('messages.index') }}">Messages</a><a href="{{ route('notifications.index') }}">Notifications</a>@endif
            </div>
            <div>
                <strong>Account</strong>
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="footer-link" type="submit">Log out</button></form>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    @if ($canRegister)<a href="{{ route('register') }}">Create account</a>@endif
                @endauth
            </div>
        </div>
        @if ($branding?->show_platform_branding ?? true)
            <div class="container powered">Powered by <a href="{{ config('brand.url', 'https://privategather.com') }}" rel="noopener">Private Gather</a></div>
        @endif
    @endif
</footer>
<script>
if('serviceWorker' in navigator && window.isSecureContext){window.addEventListener('load',()=>navigator.serviceWorker.register('{{ \App\Support\MountUrl::to('/sw.js') }}?v={{ rawurlencode($releaseVersion) }}').catch(()=>{}));}
</script>
</body>
</html>
