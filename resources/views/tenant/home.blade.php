@extends('layouts.app')
@section('title',$tenant->name)
@section('content')
@if(request()->attributes->get('tenant_preview'))
<div class="container flash"><div class="notice"><strong>Website preview:</strong> this is the generated homepage for {{$tenant->name}}. Use My Sites → Manage Website to edit it. The public subdomain will work when its DNS is available.</div></div>
@endif
@if($page)
@include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events])
@else
<section class="page-hero"><div class="container"><h1>{{$tenant->name}}</h1><p>No homepage has been published yet.</p></div></section>
@endif
@endsection
