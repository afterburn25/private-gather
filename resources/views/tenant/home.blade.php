@extends('layouts.tenant-site')
@section('title',$tenant->name)
@section('content')
@if(request()->attributes->get('tenant_preview'))
<div class="tenant-shell" style="padding-top:18px"><div class="tenant-flash"><strong>Website preview:</strong> You are viewing the generated {{$tenant->name}} tenant website. Manage pages, navigation, media, branding and theme from Club OS. Public DNS availability is separate from this preview.</div></div>
@endif
@if($page)
@include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events])
@else
<section class="pg-page-hero tenant-site-hero"><div class="tenant-shell"><span class="pg-eyebrow">{{strtoupper($tenant->isClub() ? 'PRIVATE CLUB COMMUNITY' : 'PRIVATE COMMUNITY')}}</span><h1>{{$tenant->name}}</h1><p>This community has not published its homepage yet.</p>@auth<div class="pg-actions" style="margin-top:22px"><a class="tenant-button tenant-button-ghost" href="{{route('dashboard')}}">My Private Gather</a></div>@endauth</div></section>
@endif
@endsection
