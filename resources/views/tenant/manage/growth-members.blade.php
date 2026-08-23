@extends('layouts.app')
@section('title','Member Memberships · '.$tenant->name)
@section('content')
<section class="container section">
<div class="panel-head"><div><div class="eyebrow">MEMBER MEMBERSHIPS</div><h1>{{$tenant->name}}</h1><p class="muted">Assign membership levels and track organization-level membership dates and billing state.</p></div><div class="row-actions"><a class="btn" href="{{route('tenant.growth.index')}}">Growth & Commerce</a><a class="btn" href="{{route('tenant.dashboard')}}">Control Center</a></div></div>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
@if($errors->any())<div class="notice danger"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<form class="panel form-inline" method="get"><input name="q" value="{{request('q')}}" placeholder="Search members"><button class="btn">Search</button></form>
<div style="display:grid;gap:16px;margin-top:18px">
@forelse($members as $member)
<form class="panel form-stack" method="post" action="{{route('tenant.growth.members.update',$member->id)}}">@csrf @method('PATCH')
<div class="row-between"><div><strong>{{$member->display_name ?: $member->name}}</strong><small>{{$member->email}} · Account {{$member->user_status}}</small></div><span class="badge">{{$member->membership_status}}</span></div>
<div class="form-inline"><select name="status"><option value="pending" @selected($member->membership_status==='pending')>Pending</option><option value="active" @selected($member->membership_status==='active')>Active</option><option value="suspended" @selected($member->membership_status==='suspended')>Suspended</option><option value="banned" @selected($member->membership_status==='banned')>Banned</option></select><select name="membership_level_id"><option value="">No level</option>@foreach($membershipLevels as $level)<option value="{{$level->id}}" @selected((int)$member->membership_level_id===(int)$level->id)>{{$level->name}}</option>@endforeach</select><select name="membership_billing_status"><option value="">No billing state</option>@foreach(['active','pending','past_due','canceled','comped'] as $state)<option value="{{$state}}" @selected($member->membership_billing_status===$state)>{{str_replace('_',' ',ucfirst($state))}}</option>@endforeach</select></div>
<div class="form-inline"><label>Started <input name="membership_started_at" type="date" value="{{$member->membership_started_at?substr((string)$member->membership_started_at,0,10):''}}"></label><label>Expires <input name="membership_expires_at" type="date" value="{{$member->membership_expires_at?substr((string)$member->membership_expires_at,0,10):''}}"></label></div><button class="btn primary">Save membership</button>
</form>
@empty<div class="panel"><p class="muted">No member accounts match this search.</p></div>@endforelse
</div>
{{$members->links()}}
</section>
@endsection
