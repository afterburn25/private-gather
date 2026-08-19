@extends('layouts.app')
@section('title','Members')
@section('content')
<div class="container section">
    <div class="panel-head">
        <div>
            <div class="eyebrow">SELF-HOSTED MEMBER ADMINISTRATION</div>
            <h1>Members</h1>
            <p class="muted">Review registrations for {{$tenant->name}} and control whether each local account can sign in.</p>
        </div>
        <a class="btn" href="{{route('tenant.dashboard')}}">Back to Control Center</a>
    </div>

    @if(session('status'))<div class="notice">{{session('status')}}</div>@endif

    <form method="get" action="{{route('tenant.members.index')}}" class="panel form-inline">
        <input name="q" value="{{request('q')}}" placeholder="Search name or email" aria-label="Search members">
        <button type="submit">Search</button>
        @if(request('q'))<a class="btn" href="{{route('tenant.members.index')}}">Clear</a>@endif
    </form>

    <div class="panel">
        @forelse($members as $member)
            <div class="row-between">
                <div>
                    <strong>{{$member->display_name ?: $member->name}}</strong>
                    <small>{{$member->email}} · {{$member->pivot->role}} · Account: {{$member->status}}</small>
                </div>
                <form method="post" action="{{route('tenant.members.update',$member)}}" class="form-inline">
                    @csrf
                    @method('patch')
                    <select name="status" aria-label="Account status for {{$member->display_name ?: $member->name}}">
                        <option value="pending" @selected($member->status==='pending')>Pending</option>
                        <option value="active" @selected($member->status==='active')>Active</option>
                        <option value="suspended" @selected($member->status==='suspended')>Suspended</option>
                        <option value="banned" @selected($member->status==='banned')>Banned</option>
                    </select>
                    <button type="submit">Update</button>
                </form>
            </div>
        @empty
            <p class="muted">No members match this search.</p>
        @endforelse
    </div>

    {{$members->links()}}
</div>
@endsection
