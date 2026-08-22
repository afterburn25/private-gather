@php
$adminUser=auth()->user();
$adminName=$adminUser?->display_name ?: $adminUser?->name ?: 'Administrator';
$version=is_file(base_path('VERSION'))?trim((string)file_get_contents(base_path('VERSION'))):'';
$isHosted=\App\Support\Edition::isHosted();
$editionLabel=$isHosted?'Hosted Edition':'Self-Hosted Edition';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{csrf_token()}}">
    <title>@yield('title','Administration') — {{config('app.name','Private Gather')}}</title>
    <link rel="icon" type="image/webp" href="{{\App\Support\MountUrl::to('/assets/branding/private-gather-crest-gold.webp')}}">
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/app.css')}}">
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/admin.css')}}">
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/private-gather-luxury-2026.css')}}?v=luxury-r12">
</head>
<body class="pg-admin pg-lux-admin">
<div class="pg-admin-shell">
    <aside class="pg-admin-sidebar">
        <a class="pg-admin-brand" href="{{route('admin.home')}}">
            <img src="{{\App\Support\MountUrl::to('/assets/branding/private-gather-crest-gold.webp')}}" alt="Private Gather">
            <span><strong>Private Gather</strong><small>{{$isHosted?'CONTROL CENTER':'SELF-HOSTED'}}</small></span>
        </a>

        <nav class="pg-admin-nav" aria-label="Administration">
            <div class="pg-admin-nav-group">
                <span class="pg-admin-nav-label">Overview</span>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.home')?'active':''}}" href="{{route('admin.home')}}"><span class="pg-admin-nav-icon">OV</span><span>Dashboard</span></a>
            </div>
            <div class="pg-admin-nav-group">
                <span class="pg-admin-nav-label">{{$isHosted?'Platform':'Installation'}}</span>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.users.*')?'active':''}}" href="{{route('admin.users.index')}}"><span class="pg-admin-nav-icon">US</span><span>Users</span></a>
                @if($isHosted)
                <a class="pg-admin-nav-link {{request()->routeIs('admin.tenants.*')?'active':''}}" href="{{route('admin.tenants.index')}}"><span class="pg-admin-nav-icon">OR</span><span>Organizations</span></a>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.plans.*')?'active':''}}" href="{{route('admin.plans.index')}}"><span class="pg-admin-nav-icon">PL</span><span>Plans</span></a>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.content.*')?'active':''}}" href="{{route('admin.content.edit')}}"><span class="pg-admin-nav-icon">WB</span><span>Platform Website</span></a>
                @else
                <a class="pg-admin-nav-link" href="{{route('tenant.dashboard')}}"><span class="pg-admin-nav-icon">SI</span><span>Manage Site</span></a>
                @endif
            </div>
            <div class="pg-admin-nav-group">
                <span class="pg-admin-nav-label">Operations</span>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.moderation.*')?'active':''}}" href="{{route('admin.moderation.index')}}"><span class="pg-admin-nav-icon">MD</span><span>Moderation</span></a>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.age-verification.*')?'active':''}}" href="{{route('admin.age-verification.index')}}"><span class="pg-admin-nav-icon">ID</span><span>Age & Identity</span></a>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.privacy-requests.*')?'active':''}}" href="{{route('admin.privacy-requests.index')}}"><span class="pg-admin-nav-icon">PR</span><span>Privacy Requests</span></a>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.health.*')?'active':''}}" href="{{route('admin.health.index')}}"><span class="pg-admin-nav-icon">HL</span><span>System Health</span></a>
                <a class="pg-admin-nav-link {{request()->routeIs('admin.upgrades.*')?'active':''}}" href="{{route('admin.upgrades.index')}}"><span class="pg-admin-nav-icon">UP</span><span>Update Center</span></a>
            </div>
        </nav>

        <div class="pg-admin-sidebar-footer">
            <a class="pg-admin-site-link" href="{{\App\Support\MountUrl::to('/')}}" target="_blank" rel="noopener"><span>View public website</span><strong>↗</strong></a>
            @if($version!=='')<div class="pg-admin-version"><span>{{$editionLabel}}</span><strong>v{{$version}}</strong></div>@endif
        </div>
    </aside>

    <section class="pg-admin-surface">
        <header class="pg-admin-toolbar">
            <div class="pg-admin-toolbar-title">
                <span class="pg-admin-mobile-mark">PG</span>
                <div><small>{{$isHosted?'PLATFORM ADMINISTRATION':'LOCAL ADMINISTRATION'}}</small><strong>@yield('title','Administration')</strong></div>
            </div>
            <div class="pg-admin-toolbar-actions">
                <div class="pg-admin-user"><span>{{strtoupper(substr($adminName,0,1))}}</span><div><strong>{{$adminName}}</strong><small>{{$isHosted?'Platform administrator':'Installation administrator'}}</small></div></div>
                <form method="post" action="{{route('admin.logout')}}">@csrf<button class="pg-admin-signout" type="submit">Sign out</button></form>
            </div>
        </header>

        <main class="pg-admin-content">
            @if(session('status'))<div class="alert alert-success">{{session('status')}}</div>@endif
            @if(session('success'))<div class="alert alert-success">{{session('success')}}</div>@endif
            @yield('content')
        </main>
    </section>
</div>
</body>
</html>