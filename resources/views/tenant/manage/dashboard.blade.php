@extends('layouts.app')
@section('title','Manage '.$tenant->name)
@section('content')
<section class="container section">
<div class="panel-head"><div><div class="eyebrow">ORGANIZER CONTROL CENTER</div><h1>{{$tenant->name}}</h1><p class="muted">Manage your hosted website, events, branding, domains and staff from one place.</p></div><div class="row-actions"><a class="btn" target="_blank" rel="noopener" href="{{route('organizations.index',['preview'=>$tenant->id])}}">Preview Website</a><a class="btn" href="{{route('organizations.index')}}">My Sites</a></div></div>
<div class="stats"><div class="stat"><strong>{{$tenant->events_count}}</strong><span>Events</span></div><div class="stat"><strong>{{$tenant->domains->count()}}</strong><span>Domains</span></div><div class="stat"><strong>{{ucfirst($tenant->subscription?->plan?->name?:$tenant->plan)}}</strong><span>Plan</span></div></div>
<div class="dashboard-grid"><a class="panel action" href="{{route('tenant.events.index')}}">Manage Events</a><a class="panel action" href="{{route('tenant.cms.pages')}}">Website & CMS</a><a class="panel action" href="{{route('tenant.domains.index')}}">Domains</a><a class="panel action" href="{{route('tenant.branding.edit')}}">Branding</a><a class="panel action" href="{{route('tenant.site-settings.edit')}}">Site Settings</a><a class="panel action" href="{{route('tenant.navigation.index')}}">Navigation</a><a class="panel action" href="{{route('tenant.media.index')}}">Media</a><a class="panel action" href="{{route('tenant.orders.index')}}">Orders</a><a class="panel action" href="{{route('tenant.analytics.index')}}">Analytics</a><a class="panel action" href="{{route('tenant.staff.index')}}">Staff</a></div>
</section>
@endsection
