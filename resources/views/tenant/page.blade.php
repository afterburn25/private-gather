@extends('layouts.tenant-site')
@section('title',$page->seo_title?:$page->title.' — '.$tenant->name)
@section('meta_description',$page->seo_description?:'')
@push('head')
<meta property="og:title" content="{{ $page->social_title ?: ($page->seo_title ?: $page->title) }}">
<meta property="og:description" content="{{ $page->social_description ?: ($page->seo_description ?: '') }}">
@if($page->social_image_url && \App\Support\MountUrl::to($page->social_image_url)!=='')<meta property="og:image" content="{{ \App\Support\MountUrl::to($page->social_image_url) }}">@endif
<meta property="og:type" content="website">
@endpush
@section('content')@include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events,'chapters'=>$chapters??collect()])@endsection
