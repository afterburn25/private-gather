@extends('layouts.app')
@section('title','News · '.$tenant->name)
@section('content')
<section class="shell section pg-community-page"><div class="panel-head"><div><span class="eyebrow">CLUB NEWS</span><h1>{{ $tenant->name }} News & Updates</h1><p class="muted">Announcements, event updates and community stories from the club.</p></div><a class="button button-ghost" href="{{ route('site.home') }}">Club Home</a></div>
<div class="pg-news-grid">@forelse($posts as $post)<article class="panel pg-news-card">@if($post->is_pinned)<span class="status-pill status-ok">Pinned</span>@endif<span class="eyebrow">{{ strtoupper($post->visibility) }}</span><h2><a href="{{ route('club.news.show',$post) }}">{{ $post->title }}</a></h2><p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body),180) }}</p><small>{{ $post->published_at?->format('M j, Y') }}</small><a class="text-link" href="{{ route('club.news.show',$post) }}">Read more →</a></article>@empty<div class="panel"><p class="muted">No club news has been published yet.</p></div>@endforelse</div>{{ $posts->links() }}</section>
@endsection
