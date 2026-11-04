@extends('layouts.admin')
@section('title','System Health')
@section('content')
<div class="pg-admin-page-head"><div><span class="pg-admin-kicker">Operations</span><h1>System Health</h1><p>Private Gather {{$report['version']}} · generated {{$report['generated_at']}}</p></div></div>
<section class="pg-admin-card">@foreach($report['checks'] as $name=>$c)<div class="health-row"><span class="badge {{$c['ok']?'success':'danger'}}">{{$c['ok']?'PASS':'FAIL'}}</span><strong>{{str_replace('_',' ',ucwords($name,'_'))}}</strong><span>{{$c['value']}}</span></div>@endforeach</section>
@endsection
