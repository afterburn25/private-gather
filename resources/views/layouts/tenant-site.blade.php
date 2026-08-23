@php
    $tenant = $layoutTenant ?? ($tenant ?? app(\App\Tenancy\TenantContext::class)->requireTenant());
    $tenant->loadMissing('branding');
    $branding = $tenant->branding;
    $tenantThemeKey = \App\Support\ThemeCatalog::tenantPresetFromTheme($branding?->theme);
    $tenantTheme = \App\Support\ThemeCatalog::tenant($tenantThemeKey);
    $headerNav = $tenantNavigation['header'] ?? collect();
    $footerNav = $tenantNavigation['footer'] ?? collect();
    $displayName = auth()->check() ? (auth()->user()->display_name ?: auth()->user()->name) : null;
    $membership = auth()->check() ? auth()->user()->tenants()->whereKey($tenant->id)->first()?->pivot : null;
    $isMember = $membership && $membership->status === 'active';
    $canManage = $isMember && in_array($membership->role, ['owner','admin','manager','staff','checkin'], true);
    $membershipApplicationStatus = null;
    if (auth()->check() && ! $isMember) {
        $membershipApplicationStatus = \App\Models\TenantMembershipApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->value('status');
    }
    $applicationCtaLabel = in_array($membershipApplicationStatus, ['pending','more_info','approved'], true) ? 'Application Status' : 'Apply to Join';
    $logo = $branding?->logo_path ? \App\Support\MountUrl::to($branding->logo_path) : null;
    $primary = ($branding?->primary_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding->primary_color)) ? $branding->primary_color : '#c49a54';
    $accent = ($branding?->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding->accent_color)) ? $branding->accent_color : '#6f2948';
@endphp
<!doctype html>
<html lang="en" data-pwa data-tenant-theme="{{$tenantTheme['key']}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="{{$accent}}">
    <meta name="csrf-token" content="{{csrf_token()}}">
    <title>@yield('title',$tenant->name)</title>
    <meta name="description" content="@yield('meta_description',$tenant->name.' private community website')">
    @if($branding?->favicon_path)<link rel="icon" href="{{\App\Support\MountUrl::to($branding->favicon_path)}}">@endif
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/app.css')}}">
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/redesign.css')}}">
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/tenant-themes.css')}}">
    <style>:root{--tenant-primary:{{$primary}};--tenant-accent:{{$accent}}}@if($branding?->font_family) body{font-family:{{json_encode($branding->font_family)}},sans-serif}@endif</style>
    @stack('head')
</head>
<body class="tenant-site tenant-theme-{{$tenantTheme['key']}} tenant-layout-{{$tenantTheme['layout']}}">
<header class="tenant-header">
    <div class="tenant-header-inner">
        <a class="tenant-brand" href="{{route('site.home')}}">
            @if($logo)<img src="{{$logo}}" alt="{{$tenant->name}} logo">@else<span class="tenant-monogram">{{strtoupper(substr($tenant->name,0,2))}}</span>@endif
            <span class="tenant-brand-copy"><strong>{{$tenant->name}}</strong><small>{{data_get($tenant->settings,'marketplace_summary') ?: 'Private community'}}</small></span>
        </a>
        <nav class="tenant-nav" aria-label="{{$tenant->name}} navigation">
            @if($headerNav->isNotEmpty())
                @foreach($headerNav as $item)<a href="{{\App\Support\MountUrl::to($item->url)}}">{{$item->label}}</a>@endforeach
            @else
                <a href="{{route('site.home')}}">Home</a>
                <a href="{{route('site.events')}}">Events</a>
                <a href="{{route('site.about')}}">About</a>
                @if($isMember && \Illuminate\Support\Facades\Route::has('community.index'))<a href="{{route('community.index')}}">Community</a>@endif
            @endif
        </nav>
        <div class="tenant-actions">
            @auth
                @if($canManage)<a class="tenant-button tenant-button-ghost" href="{{route('tenant.dashboard')}}">Club OS</a>@endif
                @if($isMember)<a class="tenant-button tenant-button-primary" href="{{route('dashboard')}}">My account</a>@else<a class="tenant-button tenant-button-primary" href="{{route('membership.apply')}}">{{$applicationCtaLabel}}</a>@endif
            @else
                <a class="tenant-button tenant-button-ghost" href="{{route('login')}}">Log in</a>
                <a class="tenant-button tenant-button-primary" href="{{route('membership.apply')}}">Apply to Join</a>
            @endauth
            <button type="button" class="tenant-menu-toggle" data-tenant-menu-toggle aria-expanded="false" aria-controls="tenant-mobile-nav">☰</button>
        </div>
    </div>
    <nav id="tenant-mobile-nav" class="tenant-mobile-nav" data-tenant-mobile-nav>
        <a href="{{route('site.home')}}">Home</a><a href="{{route('site.events')}}">Events</a><a href="{{route('site.about')}}">About</a>
        @if($isMember && \Illuminate\Support\Facades\Route::has('community.index'))<a href="{{route('community.index')}}">Community</a><a href="{{route('messages.index')}}">Messages</a>@endif
        @auth
            <a href="{{route('dashboard')}}">My Private Gather</a>
            @if(! $isMember)<a href="{{route('membership.apply')}}">{{$applicationCtaLabel}}</a>@endif
        @endauth
    </nav>
</header>
<main class="tenant-main">
    @if(session('status'))<div class="tenant-shell"><div class="tenant-flash">{{session('status')}}</div></div>@endif
    @yield('content')
</main>
<footer class="tenant-footer"><div class="tenant-shell tenant-footer-grid"><div><div class="tenant-brand">@if($logo)<img src="{{$logo}}" alt="">@else<span class="tenant-monogram">{{strtoupper(substr($tenant->name,0,2))}}</span>@endif<span class="tenant-brand-copy"><strong>{{$tenant->name}}</strong><small>Private community</small></span></div><p>{{data_get($tenant->settings,'marketplace_summary') ?: 'Events, membership and community on your terms.'}}</p></div><div class="tenant-footer-links"><strong>Explore</strong>@if($footerNav->isNotEmpty())@foreach($footerNav as $item)<a href="{{\App\Support\MountUrl::to($item->url)}}">{{$item->label}}</a>@endforeach @else<a href="{{route('site.events')}}">Events</a><a href="{{route('site.about')}}">About</a>@endif</div><div class="tenant-footer-links"><strong>Members</strong>@auth<a href="{{route('dashboard')}}">My account</a>@if($isMember)<a href="{{route('messages.index')}}">Messages</a>@else<a href="{{route('membership.apply')}}">{{$applicationCtaLabel}}</a>@endif @else<a href="{{route('login')}}">Log in</a><a href="{{route('membership.apply')}}">Apply to Join</a>@endauth</div></div>@if($branding?->show_platform_branding ?? true)<div class="tenant-shell tenant-powered">Powered by Private Gather · {{$tenantTheme['label']}} tenant theme</div>@endif</footer>
<script>
(()=>{const toggle=document.querySelector('[data-tenant-menu-toggle]'),nav=document.querySelector('[data-tenant-mobile-nav]');if(!toggle||!nav)return;toggle.addEventListener('click',()=>{const open=nav.classList.toggle('is-open');toggle.setAttribute('aria-expanded',open?'true':'false')});nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{nav.classList.remove('is-open');toggle.setAttribute('aria-expanded','false')}));})();
</script>
@stack('scripts')
</body>
</html>
