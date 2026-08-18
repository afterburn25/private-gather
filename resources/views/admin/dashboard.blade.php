@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">Overview</span><h1>Private Gather Dashboard</h1><p>Monitor the platform, organizations, users, moderation activity and system operations from one place.</p></div></div>
<div class="pg-admin-metrics">@foreach($stats as $label=>$value)<div class="pg-admin-metric"><strong>{{$value}}</strong><span>{{str_replace('_',' ',ucfirst($label))}}</span></div>@endforeach</div>
<section class="pg-admin-card"><div class="pg-admin-card-head"><h2>Recent organizations</h2><a class="text-link" href="{{route('admin.tenants.index')}}">Manage organizations</a></div><div class="pg-admin-list">@forelse($recentTenants as $t)<div class="pg-admin-list-row"><div><strong>{{$t->name}}</strong><small>{{$t->type}}@if($t->primaryDomain) · {{$t->primaryDomain->domain}}@endif</small></div><span class="pg-admin-status">{{$t->status}}</span></div>@empty<div class="muted">No organizations have been created yet.</div>@endforelse</div></section>
@endsection
