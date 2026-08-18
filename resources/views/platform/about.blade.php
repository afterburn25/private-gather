@extends('layouts.app')
@section('title','About — '.($platformContent['brand_name']??config('app.name')))
@section('content')
<section class="container section narrow"><div class="eyebrow">ABOUT</div><h1>{{$platformContent['about_heading']}}</h1>@if($platformContent['about_image_url'])<img class="content-image" src="{{\App\Support\MountUrl::to($platformContent['about_image_url'])}}" alt="">@endif<div class="panel prose">{!!nl2br(e($platformContent['about_body']))!!}</div></section>
@endsection
