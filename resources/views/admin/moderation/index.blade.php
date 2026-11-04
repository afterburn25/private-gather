@extends('layouts.admin')
@section('title','Moderation')
@section('content')
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">Operations</span><h1>Moderation</h1><p>Review and resolve reports submitted across Private Gather.</p></div></div>
<div class="pg-admin-list">@forelse($reports as $r)<form class="panel" method="post" action="{{route('admin.moderation.update',$r)}}">@csrf @method('patch')<div class="row-between"><div><strong>#{{$r->id}} · {{$r->category}}</strong><small>{{$r->status}}</small><p>{{$r->details}}</p></div><div class="inline-controls"><select name="status">@foreach(['open','reviewing','resolved','dismissed'] as $s)<option @selected($r->status===$s)>{{$s}}</option>@endforeach</select><button type="submit">Update</button></div></div></form>@empty<div class="pg-admin-card muted">No moderation reports are waiting for review.</div>@endforelse</div>{{$reports->links()}}
@endsection
