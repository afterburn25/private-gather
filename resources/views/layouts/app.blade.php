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
    $discoverUrl = $isHosted && \Illuminate\Support\Facades\Route::has('discover.index') ? route('discover.index') : route('site.events');
    $isPlatformHome = ! $tenant && request()->routeIs('site.home');
@endphp
<!doctype html>
<!-- PG-HOMEPAGE-DESIGN: 1.4.5 -->
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
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/redesign-1.4.5.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\MountUrl::to('/assets/platform-themes.css') }}">
    <style>
        :root { --gold: {{ $primary }}; --gold2: {{ $primary }}; --plum: {{ $accent }}; }
        @if ($branding?->font_family)
            body { font-family: {{ json_encode($branding->font_family) }}, sans-serif; }
        @endif
    </style>
    @stack('head')
</head>
<body class="redesign-body {{ $isAdmin ? 'admin-body' : '' }} {{ $isPlatformHome ? 'pg-platform-home' : '' }} {{ $platformThemeClass }}">
<header class="pg-header {{ $isPlatformHome ? 'pg-home-header' : '' }}">
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
                <img class="platform-brand-logo" src="{{ $platformLogo }}?v=1.4.5" alt="{{ $platformName }} logo">
                <span class="pg-brand-copy"><span>{{ $platformName }}</span><small>Private by nature</small></span>
            @endif
        </a>

        <nav class="pg-primary-nav" aria-label="Primary">
            @if ($tenant && $headerNav->isNotEmpty())
                @foreach ($headerNav as $item)
                    <a href="{{ \App\Support\MountUrl::to($item->url) }}">{{ $item->label }}</a>
                @endforeach
            @elseif(auth()->check() && ! $tenant)
                <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Home</a>
                <a class="{{ request()->routeIs('discover.*') ? 'is-active' : '' }}" href="{{ $discoverUrl }}">Discover</a>
                <a class="{{ request()->routeIs('site.events','events.*') ? 'is-active' : '' }}" href="{{ route('site.events') }}">Events</a>
                @if($isHosted)<a class="{{ request()->routeIs('site.organizations','clubs.*') ? 'is-active' : '' }}" href="{{ route('site.organizations') }}">Clubs</a>@endif
                @if($isHosted && \Illuminate\Support\Facades\Route::has('network-community.index'))<a class="{{ request()->routeIs('network-community.*') ? 'is-active' : '' }}" href="{{ route('network-community.index') }}">Community</a>@endif
                @if(\Illuminate\Support\Facades\Route::has('community.groups.index') && $isCommunityMember)<a class="{{ request()->routeIs('community.groups.*') ? 'is-active' : '' }}" href="{{ route('community.groups.index') }}">Groups</a>@endif
                <a class="{{ request()->routeIs('messages.*') ? 'is-active' : '' }}" href="{{ route('messages.index') }}">Messages</a>
                @if(\Illuminate\Support\Facades\Route::has('saved.index'))<a class="{{ request()->routeIs('saved.*') ? 'is-active' : '' }}" href="{{ route('saved.index') }}">Saved</a>@endif
                @if(\Illuminate\Support\Facades\Route::has('notifications.index'))<a class="{{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" href="{{ route('notifications.index') }}">Notifications</a>@endif
            @else
                <a class="{{ request()->routeIs('site.home') ? 'is-active' : '' }}" href="{{ route('site.home') }}">Home</a>
                <a class="{{ request()->routeIs('site.events','events.*') ? 'is-active' : '' }}" href="{{ route('site.events') }}">Gatherings</a>
                @if (! $tenant && $isHosted)<a class="{{ request()->routeIs('site.organizations','clubs.*') ? 'is-active' : '' }}" href="{{ route('site.organizations') }}">Clubs</a>@endif
                @if (! $tenant && $canRegister)<a href="{{ route('register') }}">Membership</a>@endif
                @if ($isCommunityMember && \Illuminate\Support\Facades\Route::has('community.groups.index'))<a href="{{ route('community.groups.index') }}">Groups</a>@endif
                <a class="{{ request()->routeIs('site.about') ? 'is-active' : '' }}" href="{{ route('site.about') }}">About</a>
            @endif
        </nav>

        <div class="pg-header-actions">
            @if (auth()->check())
                @if ($isPlatformAdmin)<a class="button button-ghost" href="{{ route('admin.home') }}">Admin</a>@endif
                @if ($canManage)
                    <a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Club OS</a>
                @elseif ($tenant && $isHosted && ! $isCommunityMember)
                    <a class="button button-ghost" href="{{ route('membership.apply') }}">{{ in_array($membershipApplicationStatus, ['pending', 'more_info', 'approved'], true) ? 'Application' : 'Apply to Join' }}</a>
                @elseif (! $tenant && $isHosted)
                    <a class="button button-ghost" href="{{ route('organizations.index') }}">My clubs</a>
                @endif
                <a class="pg-avatar-button" href="{{ route('profile.edit') }}" aria-label="Open profile for {{ $displayName }}">{{ $displayInitial }}</a>
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
        @if(auth()->check())
            <a href="{{ route('dashboard') }}">Home</a>
            <a href="{{ $discoverUrl }}">Discover</a>
            <a href="{{ route('site.events') }}">Events</a>
            @if($isHosted)<a href="{{ route('site.organizations') }}">Clubs</a>@endif
            @if(!$tenant && $isHosted && \Illuminate\Support\Facades\Route::has('network-community.index'))<a href="{{ route('network-community.index') }}">Community</a>@endif
            @if(!$tenant && $isHosted && \Illuminate\Support\Facades\Route::has('network-community.groups.index'))<a href="{{ route('network-community.groups.index') }}">Network Groups</a>@elseif($isCommunityMember && \Illuminate\Support\Facades\Route::has('community.groups.index'))<a href="{{ route('community.groups.index') }}">Groups</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('members.discover'))<a href="{{ route('members.discover') }}">Members</a>@endif
            <a href="{{ route('messages.index') }}">Messages</a>
            @if(\Illuminate\Support\Facades\Route::has('saved.index'))<a href="{{ route('saved.index') }}">Saved</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('notifications.index'))<a href="{{ route('notifications.index') }}">Notifications</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('connections.index'))<a href="{{ route('connections.index') }}">Connections</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('member.membership.show'))<a href="{{ route('member.membership.show') }}">Memberships</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('member.badges.index'))<a href="{{ route('member.badges.index') }}">Badges</a>@endif
            <a href="{{ route('member.tickets') }}">Tickets</a>
            @if(\Illuminate\Support\Facades\Route::has('privacy.index'))<a href="{{ route('privacy.index') }}">Privacy</a>@endif
            <a href="{{ route('member.security') }}">Security</a>
            <a href="{{ route('profile.edit') }}">Profile</a>
            @if($canManage)<a href="{{ route('tenant.dashboard') }}">Club OS</a>@endif
            @if($isPlatformAdmin)<a href="{{ route('admin.home') }}">Admin</a>@endif
        @else
            <a href="{{ route('site.home') }}">Home</a>
            <a href="{{ route('site.events') }}">Events</a>
            @if (! $tenant && $isHosted)<a href="{{ route('site.organizations') }}">Clubs &amp; organizers</a>@endif
            <a href="{{ route('site.about') }}">About Private Gather</a>
            <a href="{{ route('login') }}">Log in</a>
            @if ($canRegister)<a href="{{ route('register') }}">Create account</a>@endif
        @endif
    </nav>
</header>

@if ($canManage)
    <div class="pg-context-bar">
        <div class="pg-shell pg-context-row" aria-label="Club operator navigation" style="overflow-x:auto;white-space:nowrap">
            <span class="pg-context-label">Club OS</span>
            <a class="{{ request()->routeIs('tenant.dashboard') ? 'is-active' : '' }}" href="{{ route('tenant.dashboard') }}">Overview</a>
            <a class="{{ request()->routeIs('tenant.events.*') ? 'is-active' : '' }}" href="{{ route('tenant.events.index') }}">Events</a>
            <a class="{{ request()->routeIs('tenant.membership-applications.*') ? 'is-active' : '' }}" href="{{ route('tenant.membership-applications.index') }}">Applications</a>
            @if(\Illuminate\Support\Facades\Route::has('tenant.membership-levels.index'))<a class="{{ request()->routeIs('tenant.membership-levels.*') ? 'is-active' : '' }}" href="{{ route('tenant.membership-levels.index') }}">Memberships</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('tenant.crm.index'))<a class="{{ request()->routeIs('tenant.crm.*') ? 'is-active' : '' }}" href="{{ route('tenant.crm.index') }}">CRM</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('tenant.commerce.index'))<a class="{{ request()->routeIs('tenant.commerce.*') ? 'is-active' : '' }}" href="{{ route('tenant.commerce.index') }}">Commerce</a>@endif
            <a class="{{ request()->routeIs('tenant.orders.*') ? 'is-active' : '' }}" href="{{ route('tenant.orders.index') }}">Orders</a>
            <a class="{{ request()->routeIs('tenant.staff.*') ? 'is-active' : '' }}" href="{{ route('tenant.staff.index') }}">Staff</a>
            @if(\Illuminate\Support\Facades\Route::has('tenant.badges.index'))<a class="{{ request()->routeIs('tenant.badges.*') ? 'is-active' : '' }}" href="{{ route('tenant.badges.index') }}">Badges</a>@endif
            <a class="{{ request()->routeIs('tenant.analytics.*') ? 'is-active' : '' }}" href="{{ route('tenant.analytics.index') }}">Reports</a>
            @if(\Illuminate\Support\Facades\Route::has('tenant.growth.index'))<a class="{{ request()->routeIs('tenant.growth.*') ? 'is-active' : '' }}" href="{{ route('tenant.growth.index') }}">Growth</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('tenant.builder.index'))<a class="{{ request()->routeIs('tenant.builder.*') ? 'is-active' : '' }}" href="{{ route('tenant.builder.index') }}">Website Builder</a>@endif
            <a class="{{ request()->routeIs('tenant.branding.*','tenant.site-settings.*','tenant.navigation.*','tenant.domains.*','tenant.cms.*') ? 'is-active' : '' }}" href="{{ route('tenant.site-settings.edit') }}">Site</a>
        </div>
    </div>
@endif

@if ($isAdmin)
    <div class="pg-context-bar">
        <div class="pg-shell pg-context-row" aria-label="Platform administration" style="overflow-x:auto;white-space:nowrap">
            <span class="pg-context-label">Platform</span>
            <a href="{{ route('admin.home') }}">Overview</a>
            @if(\Illuminate\Support\Facades\Route::has('admin.insights.index'))<a href="{{ route('admin.insights.index') }}">Insights</a>@endif
            <a href="{{ route('admin.users.index') }}">Users</a>
            @if ($isHosted)<a href="{{ route('admin.tenants.index') }}">Organizations</a><a href="{{ route('admin.plans.index') }}">Plans</a><a href="{{ route('admin.content.edit') }}">Website</a>@endif
            @if(\Illuminate\Support\Facades\Route::has('admin.badges.index'))<a href="{{ route('admin.badges.index') }}">Badges</a>@endif
            <a href="{{ route('admin.moderation.index') }}">Moderation</a>
            @if(\Illuminate\Support\Facades\Route::has('admin.trust.index'))<a href="{{ route('admin.trust.index') }}">Trust & Safety</a>@endif
            <a href="{{ route('admin.privacy-requests.index') }}">Privacy</a>
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
        <a class="{{ request()->routeIs('discover.*','site.events','events.*') ? 'is-active' : '' }}" href="{{ $discoverUrl }}"><strong>◇</strong><span>Discover</span></a>
        <a class="{{ request()->routeIs('site.events','events.*') ? 'is-active' : '' }}" href="{{ route('site.events') }}"><strong>▣</strong><span>Events</span></a>
        @if($isHosted)<a class="{{ request()->routeIs('site.organizations','clubs.*') ? 'is-active' : '' }}" href="{{ route('site.organizations') }}"><strong>◎</strong><span>Clubs</span></a>@endif
        @if(!$tenant && $isHosted && \Illuminate\Support\Facades\Route::has('network-community.index'))<a class="{{ request()->routeIs('network-community.*') ? 'is-active' : '' }}" href="{{ route('network-community.index') }}"><strong>◉</strong><span>Community</span></a>@endif
        <a class="{{ request()->routeIs('messages.*') ? 'is-active' : '' }}" href="{{ route('messages.index') }}"><strong>✉</strong><span>Messages</span></a>
        <a class="{{ request()->routeIs('profile.*','member.security','privacy.*','connections.*') ? 'is-active' : '' }}" href="{{ route('profile.edit') }}"><strong>●</strong><span>Profile</span></a>
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
                    <img class="platform-brand-logo" src="{{ $platformLogo }}?v=1.4.5" alt="{{ $platformName }} logo">
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
                @if (auth()->check() && ! $tenant && $isHosted && \Illuminate\Support\Facades\Route::has('network-community.index'))<a href="{{ route('network-community.index') }}">Community</a>@endif
                <a href="{{ route('site.about') }}">About</a>
            @endif
        </div>
        <div class="pg-footer-links">
            <strong>{{ auth()->check() ? 'Account' : 'Private Gather' }}</strong>
            @if (auth()->check())
                <a href="{{ route('dashboard') }}">Dashboard</a>
                @if(\Illuminate\Support\Facades\Route::has('privacy.index'))<a href="{{ route('privacy.index') }}">Privacy Center</a>@endif
                <a href="{{ route('profile.edit') }}">Profile</a>
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