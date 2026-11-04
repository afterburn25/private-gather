@extends('layouts.admin')
@section('title','Users')
@section('content')
@php($isHosted=\App\Support\Edition::isHosted())
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">{{$isHosted?'Platform':'Members'}}</span><h1>Users</h1><p>{{$isHosted?'Search member accounts, change account status, and control platform-administrator access.':'Approve new members, change account status, and manage local administrator access.'}}</p></div></div>
@if(!$isHosted && \App\Support\Edition::selfHostedRegistration()==='approval')<div class="notice"><strong>Approval mode is active.</strong> New registrations remain pending and cannot sign in until an administrator changes their status to active.</div>@endif
<form class="form-inline" method="get"><input name="q" value="{{request('q')}}" placeholder="Search users"><button type="submit">Search</button></form>
<div class="pg-admin-list">@forelse($users as $u)<form class="panel row-between" method="post" action="{{route('admin.users.update',$u)}}">@csrf @method('patch')<div><strong>{{$u->display_name?:$u->name}}</strong><small>{{$u->email}} · {{$u->status}} · joined {{$u->created_at}}</small></div><div class="inline-controls"><select name="status">@foreach(['pending','active','suspended','banned'] as $s)<option @selected($u->status===$s)>{{$s}}</option>@endforeach</select><label class="checkbox-line"><input type="checkbox" name="is_platform_admin" value="1" @checked($u->is_platform_admin)> {{$isHosted?'Platform admin':'Local admin'}}</label><button type="submit">Save</button></div></form>@empty<div class="pg-admin-card muted">No users matched your search.</div>@endforelse</div>{{$users->links()}}
@endsection
