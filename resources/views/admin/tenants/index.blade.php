@extends('layouts.admin')
@section('title','Organizations')
@section('content')
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">Platform</span><h1>Organizations</h1><p>Manage clubs, organizers, subscription assignments and account status.</p></div></div>
<form class="form-inline" method="get"><input name="q" value="{{request('q')}}" placeholder="Search organizations"><button type="submit">Search</button></form>
<div class="pg-admin-list">@forelse($tenants as $t)<form class="panel row-between" method="post" action="{{route('admin.tenants.update',$t)}}">@csrf @method('patch')<div><strong>{{$t->name}}</strong><small>{{$t->type}} · {{$t->primaryDomain?->domain}}</small></div><div class="inline-controls"><select name="status">@foreach(['active','suspended','closed'] as $s)<option @selected($t->status===$s)>{{$s}}</option>@endforeach</select><select name="plan_id">@foreach($plans as $p)<option value="{{$p->id}}" @selected($t->subscription?->plan_id===$p->id)>{{$p->name}}</option>@endforeach</select><button type="submit">Save</button></div></form>@empty<div class="pg-admin-card muted">No organizations matched your search.</div>@endforelse</div>{{$tenants->links()}}
@endsection
