@extends('layouts.admin')
@section('title','Users')
@section('content')
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">Platform</span><h1>Users</h1><p>Search member accounts, change account status, and control platform-administrator access.</p></div></div>
<form class="form-inline" method="get"><input name="q" value="{{request('q')}}" placeholder="Search users"><button type="submit">Search</button></form>
<div class="pg-admin-list">@forelse($users as $u)<form class="panel row-between" method="post" action="{{route('admin.users.update',$u)}}">@csrf @method('patch')<div><strong>{{$u->display_name?:$u->name}}</strong><small>{{$u->email}} · joined {{$u->created_at}}</small></div><div class="inline-controls"><select name="status">@foreach(['active','suspended','banned'] as $s)<option @selected($u->status===$s)>{{$s}}</option>@endforeach</select><label class="checkbox-line"><input type="checkbox" name="is_platform_admin" value="1" @checked($u->is_platform_admin)> Platform admin</label><button type="submit">Save</button></div></form>@empty<div class="pg-admin-card muted">No users matched your search.</div>@endforelse</div>{{$users->links()}}
@endsection
