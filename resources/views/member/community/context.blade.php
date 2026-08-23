@extends('layouts.app')
@section('title',$contextTitle)
@section('content')
<section class="shell section">
<div class="panel-head">
    <div>
        <div class="eyebrow">{{$contextKind}}</div>
        <h1>{{$contextTitle}}</h1>
        <p class="muted">{{$contextSubtitle}}</p>
    </div>
    <div class="row-actions">
        <a class="btn" href="{{$backUrl}}">Back</a>
        <a class="btn" href="{{route('community.index')}}">Club Community</a>
    </div>
</div>
@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
<form class="panel form-stack" method="post" action="{{$postAction}}">
    @csrf
    <h2>Post to this community</h2>
    <textarea name="body" rows="4" maxlength="5000" required placeholder="Share an update with members here"></textarea>
    <button class="btn primary">Post</button>
</form>
<div style="display:grid;gap:18px;margin-top:18px">
@forelse($posts as $post)
<article class="panel">
    <div class="row-between">
        <div><strong>{{$post->user?->display_name ?: $post->user?->name ?: 'Former member'}}</strong><small>{{$post->created_at->diffForHumans()}} @if($post->is_pinned) · Pinned @endif</small></div>
        <div class="row-actions">
            @if($canModerate)<form method="post" action="{{route('community.posts.pin',$post)}}">@csrf @method('PATCH')<button class="btn">{{$post->is_pinned?'Unpin':'Pin'}}</button></form>@endif
            @if($canModerate || $post->user_id===auth()->id())<form method="post" action="{{route('community.posts.destroy',$post)}}">@csrf @method('DELETE')<button class="btn">Remove</button></form>@endif
        </div>
    </div>
    <p style="white-space:pre-wrap">{{$post->body}}</p>
    @php($myReaction=$post->reactions->firstWhere('user_id',auth()->id()))
    <div class="row-actions">
        <form method="post" action="{{route('community.reactions.store',$post)}}">@csrf<select name="reaction"><option value="like">Like</option><option value="love">Love</option><option value="celebrate">Celebrate</option><option value="support">Support</option></select><button class="btn">React</button></form>
        @if($myReaction)<form method="post" action="{{route('community.reactions.destroy',$post)}}">@csrf @method('DELETE')<button class="btn">Remove {{$myReaction->reaction}}</button></form>@endif
        <span class="muted">{{$post->reactions->count()}} reactions</span>
    </div>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.08)">
        @foreach($post->comments as $comment)
        <div class="list-row">
            <div><strong>{{$comment->user?->display_name ?: $comment->user?->name ?: 'Former member'}}</strong><small>{{$comment->created_at->diffForHumans()}}</small><p>{{$comment->body}}</p></div>
            @if($canModerate || $comment->user_id===auth()->id())<form method="post" action="{{route('community.comments.destroy',$comment)}}">@csrf @method('DELETE')<button class="btn">Remove</button></form>@endif
        </div>
        @endforeach
        <form class="form-inline" method="post" action="{{route('community.comments.store',$post)}}">@csrf<input name="body" maxlength="3000" required placeholder="Write a comment"><button class="btn">Comment</button></form>
    </div>
</article>
@empty
<div class="panel"><p class="muted">No posts here yet. Start the conversation.</p></div>
@endforelse
</div>
{{$posts->links()}}
</section>
@endsection
