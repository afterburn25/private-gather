@extends('layouts.app')
@section('title','Preview — '.$page->title)
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">BUILDER PREVIEW · {{ strtoupper($device) }}</span>
        <h1>{{ $page->title }}</h1>
        <p>This authenticated preview can render draft page content. Public routes still require the page itself to be published.</p>
        <div class="pg-actions">
            <a class="button button-primary" href="{{ route('tenant.builder.pages.edit',$page) }}">Back to editor</a>
            <a class="button button-ghost" href="{{ route('tenant.builder.pages.preview',[$page,'device'=>'desktop']) }}">Desktop</a>
            <a class="button button-ghost" href="{{ route('tenant.builder.pages.preview',[$page,'device'=>'mobile']) }}">Mobile</a>
        </div>
    </div>
</section>
<section class="pg-page">
    <div class="pg-shell">
        <div class="pg-privacy-note">Preview is manager-only and does not make a draft page publicly reachable. Dynamic events and chapters use current tenant data.</div>
        <div style="margin-top:20px;overflow:auto">
            <div class="pg-card" style="padding:0;overflow:hidden;{{ $device==='mobile' ? 'max-width:430px;margin:0 auto' : 'width:100%' }}">
                <div style="padding:10px 16px;border-bottom:1px solid var(--pg-line);display:flex;justify-content:space-between;gap:12px"><strong>{{ $tenant->name }}</strong><span class="muted">{{ $device==='mobile' ? '430px mobile canvas' : 'Responsive desktop canvas' }}</span></div>
                <div inert aria-label="Non-interactive website preview">
                    @include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events,'chapters'=>$chapters])
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
