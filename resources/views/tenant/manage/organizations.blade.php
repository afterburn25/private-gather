@extends('layouts.app')
@section('title','Organizations')
@section('content')
<section class="shell section">
<div class="panel-head"><div><div class="eyebrow">ORGANIZATIONS</div><h1>Your clubs & event brands</h1><p class="muted">Manage your hosted websites here even when a tenant subdomain is not yet reachable from DNS.</p></div><a class="btn primary" href="{{ route('organizations.create') }}">Create organization</a></div>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
<div class="cards">
@forelse($tenants as $tenant)
@php($primary=$tenant->domains->firstWhere('is_primary',true))
<article class="card">
<span class="pill">{{ str_replace('_',' ',ucfirst($tenant->type)) }}</span>
<h3>{{ $tenant->name }}</h3>
<p>{{ $primary?->domain }}</p>
<div class="row-actions">
<a class="btn primary" href="{{ route('organizations.index',['workspace'=>$tenant->id]) }}">Manage Website</a>
<a class="btn" target="_blank" rel="noopener" href="{{ route('organizations.index',['preview'=>$tenant->id]) }}">Preview Website</a>
@if($primary)<a class="text-link" target="_blank" rel="noopener" href="https://{{$primary->domain}}">Open public address</a>@endif
</div>
</article>
@empty
<div class="panel"><h2>No websites yet</h2><p class="muted">Create your first Private Gather website to get a hosted homepage, event tools, branding, navigation and CMS controls.</p><a class="btn primary" href="{{route('organizations.create')}}">Create your website</a></div>
@endforelse
</div>
</section>
@endsection
