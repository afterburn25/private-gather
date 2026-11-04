@extends('layouts.app')
@section('title','Recovery Codes')
@section('content')<div class="container narrow"><h1>Save Your Recovery Codes</h1><div class="panel"><p>Store these somewhere safe. Each code is intended for one-time recovery.</p><pre>{{implode("\n",$codes)}}</pre><a class="button" href="{{route('member.security')}}">Done</a></div></div>@endsection