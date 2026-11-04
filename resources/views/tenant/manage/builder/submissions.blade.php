@extends('layouts.app')
@section('title','Website Form Inbox — '.$tenant->name)
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">WEBSITE BUILDER 2.0 · FORM INBOX</span>
        <h1>Website inquiries</h1>
        <p>Private operational messages submitted through published Contact sections. These records are not marketing subscriptions.</p>
        <div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.builder.index') }}">Back to Website Builder</a></div>
    </div>
</section>
<section class="pg-page"><div class="pg-shell">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="pg-grid">
@forelse($submissions as $submission)
<article class="pg-card">
    <div class="row-between"><div><span class="pg-pill">{{ ucfirst($submission->status) }}</span><strong style="display:block;margin-top:8px">{{ $submission->name }}</strong><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></div><small>{{ $submission->submitted_at?->format('M j, Y · g:i A') }}</small></div>
    <p class="muted">{{ $submission->page?->title ?: 'Deleted page' }} · {{ $submission->section?->name ?: 'Deleted contact section' }}</p>
    <div class="prose">{!! nl2br(e($submission->message)) !!}</div>
    <div class="pg-privacy-note">Privacy acknowledgement recorded: {{ $submission->privacy_ack ? 'Yes' : 'No' }}. No marketing consent is inferred from this inquiry.</div>
    <form method="post" action="{{ route('tenant.builder.submissions.update',$submission) }}" class="form-inline" style="margin-top:14px">@csrf @method('PATCH')
        <select name="status"><option value="new" @selected($submission->status==='new')>New</option><option value="reviewed" @selected($submission->status==='reviewed')>Reviewed</option><option value="closed" @selected($submission->status==='closed')>Closed</option></select>
        <button class="button button-ghost">Update status</button>
    </form>
</article>
@empty
<div class="pg-card"><h3>No website inquiries yet</h3><p class="muted">Publish a Contact section to accept privacy-acknowledged website messages.</p></div>
@endforelse
</div>
@if($submissions->hasPages())<div style="margin-top:24px">{{ $submissions->links() }}</div>@endif
</div></section>
@endsection
