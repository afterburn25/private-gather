@extends('layouts.app')
@section('title','Website Builder 2.0 — '.$tenant->name)
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">CLUB OS · WEBSITE BUILDER 2.0</span>
        <h1>Build {{ $tenant->name }} without touching code.</h1>
        <p>Manage pages, reusable sections, responsive previews, search/social metadata, forms, navigation and your site shell from the same tenant-scoped workspace.</p>
        <div class="pg-actions">
            <a class="button button-primary" href="{{ route('tenant.cms.create') }}">Create page</a>
            <a class="button button-ghost" href="{{ route('tenant.navigation.index') }}">Navigation</a>
            <a class="button button-ghost" href="{{ route('tenant.site-settings.edit') }}">Header & footer</a>
            <a class="button button-ghost" href="{{ route('tenant.branding.edit') }}">Branding</a>
            <a class="button button-ghost" href="{{ route('tenant.builder.submissions.index') }}">Form inbox@if($newSubmissionCount) · {{ $newSubmissionCount }} new@endif</a>
        </div>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell">
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="pg-section">
            <div class="pg-section-head">
                <div><span class="pg-eyebrow">PAGES</span><h2>Page workspace</h2><p>Drafts remain private. Preview can render draft content only inside the authenticated manager workspace.</p></div>
                <a class="button button-ghost" href="{{ route('tenant.cms.pages') }}">Page administration</a>
            </div>
            <div class="pg-grid pg-grid-3">
                @forelse($pages as $page)
                    <article class="pg-card">
                        <div class="row-between"><div><span class="pg-pill">{{ ucfirst($page->status) }}</span>@if($page->is_homepage)<span class="pg-pill">Homepage</span>@endif</div><strong>{{ $page->sections_count }} sections</strong></div>
                        <h3>{{ $page->title }}</h3>
                        <p class="muted">/{{ $page->slug }} · {{ $page->seo_title ?: 'SEO title uses page title' }}</p>
                        <div class="pg-actions">
                            <a class="button button-primary" href="{{ route('tenant.builder.pages.edit',$page) }}">Open Builder</a>
                            <a class="button button-ghost" href="{{ route('tenant.builder.pages.preview',[$page,'device'=>'desktop']) }}">Preview</a>
                        </div>
                    </article>
                @empty
                    <div class="pg-card"><h3>No pages yet</h3><p class="muted">Create a page, then compose it from structured sections.</p><a class="button button-primary" href="{{ route('tenant.cms.create') }}">Create first page</a></div>
                @endforelse
            </div>
        </section>

        <section class="pg-section">
            <div class="pg-section-head"><div><span class="pg-eyebrow">STARTING POINTS</span><h2>Homepage presets</h2><p>Applying a preset intentionally replaces the homepage sections, then Builder 2.0 can refine every resulting section.</p></div></div>
            <div class="pg-grid pg-grid-3">
                @foreach($templates as $key => $template)
                    <form method="post" action="{{ route('tenant.builder.template') }}" class="pg-card form-stack">
                        @csrf
                        <input type="hidden" name="template" value="{{ $key }}">
                        <h3>{{ $template['label'] }}</h3>
                        <p class="muted">{{ $template['summary'] }}</p>
                        <button class="button button-ghost" onclick="return confirm('Replace the current homepage sections with this preset?')">Apply preset</button>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="pg-section">
            <div class="pg-section-head"><div><span class="pg-eyebrow">REUSABLE BLOCKS</span><h2>Saved section library</h2><p>Reusable blocks are snapshots. Editing or archiving a saved block never rewrites copies already placed on pages.</p></div></div>
            <div class="pg-grid pg-grid-3">
                @forelse($reusableBlocks as $block)
                    <article class="pg-card">
                        <span class="pg-pill">{{ str_replace('_',' ',ucfirst($block->type)) }}</span>
                        <h3>{{ $block->name }}</h3>
                        <p class="muted">Use the page editor to insert this block into any page owned by {{ $tenant->name }}.</p>
                        <form method="post" action="{{ route('tenant.builder.reusable.archive',$block) }}">@csrf @method('DELETE')<button class="button button-ghost">Archive block</button></form>
                    </article>
                @empty
                    <div class="pg-card"><h3>No reusable blocks yet</h3><p class="muted">Save any configured section as a reusable block from its page editor.</p></div>
                @endforelse
            </div>
        </section>

        <section class="pg-section">
            <div class="pg-grid pg-grid-3">
                <a class="pg-card pg-card-interactive" href="{{ route('tenant.site-settings.edit') }}"><span class="pg-eyebrow">SITE SHELL</span><h3>Header & footer</h3><p class="muted">Tagline, footer copy, contact address, header call-to-action and social destinations.</p><span class="text-link">Edit website settings →</span></a>
                <a class="pg-card pg-card-interactive" href="{{ route('tenant.navigation.index') }}"><span class="pg-eyebrow">NAVIGATION</span><h3>Menus & links</h3><p class="muted">Control header and footer navigation with tenant-safe stored URLs.</p><span class="text-link">Edit navigation →</span></a>
                <a class="pg-card pg-card-interactive" href="{{ route('tenant.media.index') }}"><span class="pg-eyebrow">MEDIA</span><h3>Images & assets</h3><p class="muted">Upload sanitized media, then reuse its safe path in page sections and social metadata.</p><span class="text-link">Open media library →</span></a>
            </div>
        </section>
    </div>
</section>
@endsection
