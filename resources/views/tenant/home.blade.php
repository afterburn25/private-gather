@extends('layouts.app')
@section('title',$tenant->name)
@section('content')@if($page)@include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events])@else<section class="page-hero"><div class="container"><h1>{{$tenant->name}}</h1><p>No homepage has been published yet.</p></div></section>@endif @endsection