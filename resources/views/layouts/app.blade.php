@php
    $tenant = $layoutTenant ?? app(\App\Tenancy\TenantContext::class)->tenant();
    $branding = $tenant?->branding;
    $primary = ($branding?->primary_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding->primary_color)) ? $branding->primary_color : '#d9bc78';
    $accent = ($branding?->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding->accent_color)) ? $branding->accent_color : '#6d243f';
    $isAdmin = request()->routeIs('admin.*') && ! request()->routeIs('admin.login*');
    $isPlatformAdmin = auth()->check() && (bool) auth()->user()->is_platform_admin;
    $isHosted = \App\Support\Edition::isHosted();
    $canRegister = \App\Support\Edition::registrationEnabled();
    $platformTheme = \App\Support\ThemeCatalog::platform($siteSettings['theme_preset'] ?? null);
    $platformThemeClass = $tenant ? '' : 'platform-theme-'.$platformTheme['key'].' platform-layout-'.$platformTheme['layout'];
    $canManage = false;
    $isCommunityMember = false;
    $membershipApplicationStatus = null;

    if (auth()->check() && $tenant) {
        $membership = auth()->user()->tenants()->whereKey($tenant->id)->first()?->pivot;
        $isCommunityMember = $membership && $membership->status === 'active';
        $canManage = $isCommunityMember && ($isPlatformAdmin || in_array($membership->role, ['owner', 'admin', 'manager', 'staff', 'checkin'], true));

        if ($isHosted && ! $isCommunityMember) {
            $membershipApplicationStatus = \App\Models\TenantMembershipApplication::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', auth()->id())
                ->latest('id')
                ->value('status');
        }
    }

    $headerNav = $tenant ? ($tenantNavigation['header'] ?? collect()) : collect();
    $footerNav = $tenant ? ($tenantNavigation['footer'] ?? collect()) : collect();
    $platformName = $siteSettings['brand_name'] ?? config('app.name', 'Private Gather');
    $platformLogo = \App\Support\MountUrl::to('/assets/branding/private-gather-logo.png');
    $displayName = auth()->check() ? (auth()->user()->display_name ?: auth()->user()->name) : null;
    $displayInitial = $displayName ? strtoupper(substr($displayName, 0, 1)) : 'PG';
@endphp
<!doctype html>
<html lang="en" data-pwa data-platform-theme="{{ $platformTheme['key'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#09090c">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $tenant?->name ?? $platformName)</title>
    <meta name="description" content="@yield('meta_description', 'Private Gather connects consenting adults with private lifestyle events, clubs, groups and trusted organizers through privacy-focused membership and ticketing.')">
    <link rel="manifest" href="{{ \App\Support\MountUrl::to('/manifest.webmanifest') }}">

    @if ($tenant && $branding?->favicon_path)
        <link rel="icon" href="{{ \App\Support\MountUrl::to($branding->favicon_path) }}">
    @elseif (! $tenant)
        <link rel="icon" type="image/png" href="{{ $platformLogo }}">
    @endif

    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/app.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/redesign.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/platform-themes.css') }}">
    <style>
        :root { --gold: {{ $primary }}; --gold2: {{ $primary }}; --plum: {{ $accent }}; }
        @if ($branding?->font_family)
            body { font-family: {{ json_encode($branding->font_family) }}, sans-serif; }
        @endif
    </style>
    @stack('head')
</head>
<body class="redesign-body {{ $isAdmin ? 'admin-body' : '' }} {{ $platformThemeClass }}">
<header class="pg-header">
    <div class="pg-shell pg-header-row">
        <a class="pg-brand" href="{{ \App\Support\MountUrl::to('/') }}" aria-label="{{ $tenant?->name ?? $platformName }} home">
            @if ($tenant)
                @if ($branding?->logo_path)
                    <img src="{{ \App\Support\MountUrl::to($branding->logo_path) }}" alt="{{ $tenant->name }} logo">
                @else
                    <span class="pg-brand-mark">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                @endif
                <span class="pg-brand-copy"><span>{{ $tenant->name }}</span><small>Private lifestyle community</small></span>
            @else
                <img class="platform-brand-logo" src="{{ $platformLogo }}" alt="{{ $platformName }} logo">
                <span class="pg-brand-copy"><span>{{ $platformName }}</span><small>Private by nature</small></span>
            @endif
        </a>

        <nav class="pg-primary-nav" aria-label="Primary">
            @if ($tenant && $headerNav->isNotEmpty())
                @foreach ($headerNav as $item)
                    <a href="{{ \App\Support\MountUrl::to($item->url) }}">{{ $item->label }}</a>
                @endforeach
            @else
                <a class="{{ request()->routeIs('site.home') ? 'is-active' : '' }}" href="{{ route('site.home') }}">Home</a>
                <a class="{{ request()->routeIs('site.events','events.*') ? 'is-active' : '' }}" href="{{ route('site.events') }}">Discover</a>
                @if (! $tenant && $isHosted)
                    <a class="{{ request()->routeIs('site.organizations') ? 'is-active' : '' }}" href="{{ route('site.organizations') }}">Clubs</a>
                @endif
                @if ($isCommunityMember && \Illuminate\Support\Facades\Route::has('community.index'))
                    <a class="{{ request()->routeIs('community.*') ? 'is-active' : '' }}" href="{{ route('community.index') }}">Community</a>
                @endif
                <a class="{{ request()->routeIs('site.about') ? 'is-active' : '' }}" href="{{ route('site.about') }}">About</a>
            @endif
        </nav>

        <div class="pg-header-actions">
            @if (auth()->check())
                @if ($isPlatformAdmin)
                    <a class="button button-ghost" href="{{ route('admin.home') }}">Admin Backend</a>
                @endif
                @if ($canManage)
                    <a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Club OS</a>
                @elseif ($tenant && $isHosted && ! $isCommunityMember)
                    <a class="button button-ghost" href="{{ route('membership.apply') }}">
                        {{ in_array($membershipApplicationStatus, ['pending', 'more_info', 'approved'], true) ? 'Application' : 'Apply to Join' }}
                    </a>
                @elseif (! $tenant && $isHosted)
                    <a class="button button-ghost" href="{{ route('organizations.index') }}">My clubs</a>
                @endif
                <a class="pg-avatar-button" href="{{ route('dashboard') }}" aria-label="Open account for {{ $displayName }}">{{ $displayInitial }}</a>
            @else
                <a class="button button-ghost" href="{{ route('login') }}">Log in</a>
                @if ($tenant && $isHosted)
                    <a class="button button-primary" href="{{ route('membership.apply') }}">Apply to Join</a>
                @elseif ($canRegister)
                    <a class="button button-primary" href="{{ \App\Support\MountUrl::to($siteSettings['header_cta_url'] ?? route('register')) }}">{{ $siteSettings['header_cta_label'] ?? 'Join Private Gather' }}</a>
                @endif
            @endif
            <button class="pg-menu-toggle" type="button" data-pg-menu-toggle aria-expanded="false" aria-controls="pg-mobile-drawer" aria-label="Open navigation">☰</button>
        </div>
    </div>

    <nav id="pg-mobile-drawer" class="pg-mobile-drawer" data-pg-drawer aria-label="Mobile navigation">
        <a href="{{ route('site.home') }}">Home</a>
        <a href="{{ route('site.events') }}">Discover events</a>
        @if (! $tenant && $isHosted)<a href="{{ route('site.organizations') }}">Clubs &amp; organizers</a>@endif
        @if ($isCommunityMember && \Illuminate\Support\Facades\Route::has('community.index'))<a href="{{ route('community.index') }}">Community</a>@endif
        @if (auth()->check())
            <a href="{{ route('messages.index') }}">Messages</a>
            <a href="{{ route('profile.edit') }}">Profile &amp; privacy</a>
            <a href="{{ route('member.tickets') }}">Tickets</a>
            @if ($canManage)<a href="{{ route('tenant.dashboard') }}">Club OS</a>@endif
        @else
            <a href="{{ route('login') }}">Log in</a>
            @if ($canRegister)<a href="{{ route('register') }}">Create account</a>@endif
        @endif
        <a href="{{ route('site.about') }}">About Private Gather</a>
    </nav>
</header>

@if ($canManage)
    <div class="pg-context-bar">
        <div class="pg-shell pg-context-row" aria-label="Club operator navigation">
            <span class="pg-context-label">Club OS</span>
            <a class="{{ request()->routeIs('tenant.dashboard') ? 'is-active' : '' }}" href="{{ route('tenant.dashboard') }}">Overview</a>
            <a class="{{ request()->routeIs('tenant.events.*') ? 'is-active' : '' }}" href="{{ route('tenant.events.index') }}">Events</a>
            <a class="{{ request()->routeIs('tenant.membership-applications.*') ? 'is-active' : '' }}" href="{{ route('tenant.membership-applications.index') }}">Applications</a>
            <a class="{{ request()->routeIs('tenant.orders.*') ? 'is-active' : '' }}" href="{{ route('tenant.orders.index') }}">Orders</a>
            <a class="{{ request()->routeIs('tenant.staff.*') ? 'is-active' : '' }}" href="{{ route('tenant.staff.index') }}">Staff</a>
            <a class="{{ request()->routeIs('tenant.analytics.*') ? 'is-active' : '' }}" href="{{ route('tenant.analytics.index') }}">Reports</a>
            <a class="{{ request()->routeIs('tenant.branding.*','tenant.site-settings.*','tenant.navigation.*','tenant.domains.*','tenant.cms.*') ? 'is-active' : '' }}" href="{{ route('tenant.site-settings.edit') }}">Site</a>
        </div>
    </div>
@endif

@if ($isAdmin)
    <div class="pg-context-bar">
        <div class="pg-shell pg-context-row" aria-label="Platform administration">
            <span class="pg-context-label">Platform</span>
            <a href="{{ route('admin.home') }}">Overview</a>
            <a href="{{ route('admin.users.index') }}">Users</a>
            @if ($isHosted)<a href="{{ route('admin.tenants.index') }}">Organizations</a><a href="{{ route('admin.plans.index') }}">Plans</a><a href="{{ route('admin.content.edit') }}">Website</a>@endif
            <a href="{{ route('admin.moderation.index') }}">Moderation</a>
            <a href="{{ route('admin.health.index') }}">Health</a>
            <a href="{{ route('admin.upgrades.index') }}">Upgrades</a>
        </div>
    </div>
@endif

<main class="pg-main">
    @if (session('status'))
        <div class="pg-shell"><div class="pg-flash" role="status">{{ session('status') }}</div></div>
    @endif
    @yield('content')
</main>

@if (auth()->check() && ! $isAdmin)
    <nav class="pg-bottom-nav" aria-label="Member shortcuts">
        <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}"><strong>⌂</strong><span>Home</span></a>
        <a class="{{ request()->routeIs('site.events','events.*') ? 'is-active' : '' }}" href="{{ route('site.events') }}"><strong>◇</strong><span>Discover</span></a>
        @if ($isCommunityMember && \Illuminate\Support\Facades\Route::has('community.index'))
            <a class="{{ request()->routeIs('community.*') ? 'is-active' : '' }}" href="{{ route('community.index') }}"><strong>◎</strong><span>Community</span></a>
        @else
            <a class="{{ request()->routeIs('site.organizations') ? 'is-active' : '' }}" href="{{ route('site.organizations') }}"><strong>◎</strong><span>Clubs</span></a>
        @endif
        <a class="{{ request()->routeIs('messages.*') ? 'is-active' : '' }}" href="{{ route('messages.index') }}"><strong>✉</strong><span>Messages</span></a>
        <a class="{{ request()->routeIs('profile.*','member.security') ? 'is-active' : '' }}" href="{{ route('profile.edit') }}"><strong>●</strong><span>Profile</span></a>
    </nav>
@endif

<footer class="pg-footer">
    <div class="pg-shell pg-footer-grid">
        <div>
            <a class="pg-brand" href="{{ \App\Support\MountUrl::to('/') }}">
                @if ($tenant && $branding?->logo_path)
                    <img src="{{ \App\Support\MountUrl::to($branding->logo_path) }}" alt="{{ $tenant->name }} logo">
                @elseif ($tenant)
                    <span class="pg-brand-mark">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                @else
                    <img class="platform-brand-logo" src="{{ $platformLogo }}" alt="{{ $platformName }} logo">
                @endif
                <span class="pg-brand-copy"><span>{{ $tenant?->name ?? $platformName }}</span><small>Discreet. Consent-led. Community-first.</small></span>
            </a>
            <p class="muted">{{ $siteSettings['footer_text'] ?? $siteSettings['tagline'] ?? config('brand.tagline', 'Exclusive connections. Private by nature.') }}</p>
        </div>
        <div class="pg-footer-links">
            <strong>Explore</strong>
            @if ($tenant && $footerNav->isNotEmpty())
                @foreach ($footerNav as $item)<a href="{{ \App\Support\MountUrl::to($item->url) }}">{{ $item->label }}</a>@endforeach
            @else
                <a href="{{ route('site.events') }}">Events</a>
                @if (! $tenant && $isHosted)<a href="{{ route('site.organizations') }}">Clubs</a>@endif
                <a href="{{ route('site.about') }}">About</a>
            @endif
        </div>
        <div class="pg-footer-links">
            <strong>{{ auth()->check() ? 'Account' : 'Private Gather' }}</strong>
            @if (auth()->check())
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('profile.edit') }}">Profile &amp; privacy</a>
                <a href="{{ route('member.security') }}">Security</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="footer-link" type="submit">Log out</button></form>
            @else
                <a href="{{ route('login') }}">Log in</a>
                @if ($canRegister)<a href="{{ route('register') }}">Create account</a>@endif
            @endif
        </div>
    </div>
    <div class="pg-shell pg-powered">
        @if ($tenant && ($branding?->show_platform_branding ?? true))
            Powered by Private Gather@if (! $isHosted) · Self-Hosted@endif
        @elseif (! $tenant)
            Private Gather · privategather.com · For consenting adults 18+
        @endif
    </div>
</footer>
<script src="{{ \App\Support\MountUrl::to('/assets/redesign.js') }}" defer></script>
@stack('scripts')
</body>
</html>
