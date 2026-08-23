@extends('layouts.app')
@section('title',$tenant->name)
@section('content')
@if(request()->attributes->get('tenant_preview'))
<div class="pg-shell" style="padding-top:18px"><div class="pg-privacy-note"><strong>Website preview</strong> · You are viewing the generated {{ $tenant->name }} member/public site. Manage its pages, navigation, media and branding from Club OS. The public subdomain becomes reachable when its DNS is available.</div></div>
@endif
@if($page)
@include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events])
@else
<section class="pg-page-hero"><div class="pg-shell"><span class="pg-eyebrow">{{ strtoupper($tenant->isClub() ? 'PRIVATE CLUB COMMUNITY' : 'PRIVATE GATHER COMMUNITY') }}</span><h1>{{ $tenant->name }}</h1><p>This community has not published its homepage yet.</p>@auth<div class="pg-actions" style="margin-top:22px"><a class="button button-ghost" href="{{ route('dashboard') }}">My Private Gather</a></div>@endauth</div></section>
@endif
@endsection
