@extends('layouts.app')
@section('title','Organizations')
@section('content')
<section class="shell section">
<div class="panel-head"><div><div class="eyebrow">ORGANIZATIONS</div><h1>Your clubs & event brands</h1><p class="muted">Each hosted site receives its own Private Gather subdomain. Manage and preview it here while the same address is available publicly through wildcard DNS.</p></div><a class="btn primary" href="{{ route('organizations.create') }}">Create organization</a></div>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
<div class="cards">
@forelse($tenants as $tenant)
@php($primary=$tenant->domains->firstWhere('is_primary',true))
@php($publicUrl=$primary?\App\Support\TenantUrl::to($primary):'')
<article class="card">
<span class="pill">{{ str_replace('_',' ',ucfirst($tenant->type)) }}</span>
<h3>{{ $tenant->name }}</h3>
@if($publicUrl)<p><strong>Public website</strong><br><a class="text-link" target="_blank" rel="noopener" href="{{$publicUrl}}">{{$publicUrl}}</a></p>@endif
<div class="row-actions">
<a class="btn primary" href="{{ route('organizations.index',['workspace'=>$tenant->id]) }}">Manage Website</a>
<a class="btn" target="_blank" rel="noopener" href="{{ route('organizations.index',['preview'=>$tenant->id]) }}">Preview Website</a>
@if($publicUrl)<a class="btn" target="_blank" rel="noopener" href="{{$publicUrl}}">Open Public Website</a>@endif
</div>
</article>
@empty
<div class="panel"><h2>No websites yet</h2><p class="muted">Create your first Private Gather website to get a hosted homepage, event tools, branding, navigation and CMS controls.</p><a class="btn primary" href="{{route('organizations.create')}}">Create your website</a></div>
@endforelse
</div>
@if(config('platform.wildcard_enabled'))
<div class="panel" style="margin-top:24px"><div class="eyebrow">HOSTED SUBDOMAINS</div><h2>Wildcard public access</h2><p class="muted">Private Gather is configured for <code>{{\App\Support\TenantUrl::wildcardPattern()}}</code> → <code>{{\App\Support\TenantUrl::wildcardTarget()}}</code>. This DNS/SSL mapping is configured once at the hosting or DNS provider; individual sites are then created instantly inside Private Gather.</p></div>
@endif
</section>
@endsection
