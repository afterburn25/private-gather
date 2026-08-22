@extends('layouts.app')
@section('title',$post->title.' · '.$tenant->name)
@section('content')
<section class="shell section"><article class="panel pg-article"><span class="eyebrow">{{ strtoupper($post->visibility) }} · {{ $post->published_at?->format('M j, Y') }}</span><h1>{{ $post->title }}</h1>@if($post->excerpt)<p class="pg-article-lead">{{ $post->excerpt }}</p>@endif<div class="prose">{!! nl2br(e($post->body)) !!}</div><div class="row-actions"><a class="button button-ghost" href="{{ route('club.news.index') }}">Back to News</a><a class="button button-primary" href="{{ route('community.index') }}">Community</a></div></article></section>
@endsection
