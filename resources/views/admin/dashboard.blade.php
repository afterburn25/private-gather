@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
@php($isHosted=\App\Support\Edition::isHosted())
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">Overview</span><h1>Private Gather Dashboard</h1><p>{{$isHosted?'Monitor the platform, organizations, users, global badges, moderation activity and system operations from one place.':'Manage this Self-Hosted installation, member approvals, events, moderation and system operations from one place.'}}</p></div></div>
<div class="pg-admin-metrics">@foreach($stats as $label=>$value)<div class="pg-admin-metric"><strong>{{$value}}</strong><span>{{str_replace('_',' ',ucfirst($label))}}</span></div>@endforeach</div>
@if($isHosted)
<section class="pg-admin-card"><div class="pg-admin-card-head"><h2>Trust & recognition</h2><a class="text-link" href="{{ route('admin.badges.index') }}">Manage global badges</a></div><p class="muted">Create platform verification, reputation, staff and achievement badges that follow members across every club and organization.</p></section>
<section class="pg-admin-card"><div class="pg-admin-card-head"><h2>Recent organizations</h2><a class="text-link" href="{{route('admin.tenants.index')}}">Manage organizations</a></div><div class="pg-admin-list">@forelse($recentTenants as $t)<div class="pg-admin-list-row"><div><strong>{{$t->name}}</strong><small>{{$t->type}}@if($t->primaryDomain) · {{$t->primaryDomain->domain}}@endif</small></div><span class="pg-admin-status">{{$t->status}}</span></div>@empty<div class="muted">No organizations have been created yet.</div>@endforelse</div></section>
@else
<section class="pg-admin-card"><div class="pg-admin-card-head"><h2>Self-Hosted organization</h2><a class="text-link" href="{{route('tenant.dashboard')}}">Manage site</a></div>@if($localTenant)<div class="pg-admin-list-row"><div><strong>{{$localTenant->name}}</strong><small>{{str_replace('_',' ',$localTenant->type)}} · Private Gather Self-Hosted</small></div><span class="pg-admin-status">{{$localTenant->status}}</span></div>@else<div class="muted">No active Self-Hosted organization is configured.</div>@endif</section>
@endif
@endsection
