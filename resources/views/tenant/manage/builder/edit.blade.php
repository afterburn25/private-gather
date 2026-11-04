@extends('layouts.app')
@section('title','Builder — '.$page->title)
@section('content')
<section class="pg-page-hero">
    <div class="pg-shell">
        <span class="pg-eyebrow">WEBSITE BUILDER 2.0 · PAGE</span>
        <h1>{{ $page->title }}</h1>
        <p>Compose structured sections, control search/social presentation, reuse saved blocks and preview drafts without publishing them.</p>
        <div class="pg-actions">
            <a class="button button-ghost" href="{{ route('tenant.builder.index') }}">All pages</a>
            <a class="button button-primary" href="{{ route('tenant.builder.pages.preview',[$page,'device'=>'desktop']) }}">Desktop preview</a>
            <a class="button button-ghost" href="{{ route('tenant.builder.pages.preview',[$page,'device'=>'mobile']) }}">Mobile preview</a>
            <a class="button button-ghost" href="{{ route('tenant.cms.edit',$page) }}">Advanced CMS</a>
        </div>
    </div>
</section>

<section class="pg-page">
    <div class="pg-shell">
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><strong>Check the Builder fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="pg-grid pg-grid-2">
            <form method="post" action="{{ route('tenant.builder.pages.update',$page) }}" class="pg-card form-stack">
                @csrf
                @method('PATCH')
                <span class="pg-eyebrow">PAGE SETTINGS</span>
                <h2>Search & social presentation</h2>
                <label>Page title<input name="title" maxlength="160" required value="{{ old('title',$page->title) }}"></label>
                <div class="form-grid">
                    <label>SEO title<input name="seo_title" maxlength="190" value="{{ old('seo_title',$page->seo_title) }}" placeholder="Defaults to page title"></label>
                    <label>Status<select name="status"><option value="draft" @selected(old('status',$page->status)==='draft')>Draft</option><option value="published" @selected(old('status',$page->status)==='published')>Published</option></select></label>
                </div>
                <label>SEO description<textarea name="seo_description" rows="3" maxlength="500">{{ old('seo_description',$page->seo_description) }}</textarea></label>
                <div class="form-grid">
                    <label>Social title<input name="social_title" maxlength="190" value="{{ old('social_title',$page->social_title) }}" placeholder="Optional Open Graph title"></label>
                    <label>Social image path / URL<input name="social_image_url" maxlength="1000" value="{{ old('social_image_url',$page->social_image_url) }}" placeholder="/storage/media/... or https://..."></label>
                </div>
                <label>Social description<textarea name="social_description" rows="3" maxlength="500">{{ old('social_description',$page->social_description) }}</textarea></label>
                <div class="pg-privacy-note">Draft pages remain unavailable on public page routes. Builder preview is authenticated and tenant-manager only.</div>
                <button class="button button-primary">Save page settings</button>
            </form>

            <div class="pg-card form-stack">
                <span class="pg-eyebrow">ADD CONTENT</span>
                <h2>New section</h2>
                <form method="post" action="{{ route('tenant.builder.sections.store',$page) }}" class="form-stack">
                    @csrf
                    <label>Section type<select name="type" required>@foreach($sectionTypes as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Internal section name<input name="name" maxlength="120" placeholder="Optional editor label"></label>
                    <button class="button button-primary">Add section</button>
                </form>

                <hr>
                <span class="pg-eyebrow">REUSABLE LIBRARY</span>
                @forelse($reusableBlocks as $block)
                    <form method="post" action="{{ route('tenant.builder.reusable.insert',[$page,$block]) }}" class="list-row">
                        @csrf
                        <div><strong>{{ $block->name }}</strong><small>{{ str_replace('_',' ',ucfirst($block->type)) }}</small></div>
                        <button class="button button-ghost">Insert</button>
                    </form>
                @empty
                    <p class="muted">Save a configured section below to build your reusable library.</p>
                @endforelse
            </div>
        </div>

        @if($page->sections->isNotEmpty())
            <section class="pg-section">
                <div class="pg-section-head"><div><span class="pg-eyebrow">ORDER</span><h2>Section order</h2><p>Use move buttons on individual sections for quick accessible reordering, or save a complete page order here.</p></div></div>
                <form method="post" action="{{ route('tenant.builder.sections.reorder',$page) }}" class="pg-card form-stack">
                    @csrf
                    <div class="form-grid">
                        @foreach($page->sections as $position => $current)
                            <label>Position {{ $position + 1 }}
                                <select name="section_ids[]" required>
                                    @foreach($page->sections as $option)
                                        <option value="{{ $option->id }}" @selected($option->id===$current->id)>{{ $option->name ?: str_replace('_',' ',ucfirst($option->type)) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                    <button class="button button-ghost">Save complete order</button>
                </form>
            </section>
        @endif

        <section class="pg-section">
            <div class="pg-section-head"><div><span class="pg-eyebrow">CANVAS</span><h2>{{ $page->sections->count() }} sections</h2><p>All content is structured and escaped on public rendering; Builder does not accept arbitrary HTML or script blocks.</p></div></div>
            <div class="pg-grid">
                @forelse($page->sections as $section)
                    @php
                        $content = is_array($section->content) ? $section->content : [];
                        $settings = is_array($section->settings) ? $section->settings : [];
                        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
                        $itemLines = collect($items)->map(function ($item) use ($section) {
                            if (!is_array($item)) return '';
                            if ($section->type === 'faq') return ($item['question'] ?? '').' | '.($item['answer'] ?? '');
                            if ($section->type === 'testimonials') return ($item['quote'] ?? '').' | '.($item['name'] ?? '').' | '.($item['detail'] ?? '');
                            if ($section->type === 'stats') return ($item['value'] ?? '').' | '.($item['label'] ?? '');
                            if ($section->type === 'gallery') return ($item['url'] ?? '').' | '.($item['alt'] ?? '').' | '.($item['caption'] ?? '');
                            return $item['text'] ?? '';
                        })->filter()->implode("\n");
                    @endphp
                    <article class="pg-card">
                        <div class="row-between">
                            <div><span class="pg-pill">{{ str_replace('_',' ',ucfirst($section->type)) }}</span><h3>{{ $section->name ?: 'Untitled section' }}</h3><small>Sort {{ $section->sort_order }} · {{ $section->is_enabled ? 'Visible' : 'Hidden' }}</small></div>
                            <div class="pg-actions">
                                <form method="post" action="{{ route('tenant.cms.sections.move',[$section,'up']) }}">@csrf<button class="button button-ghost">↑ Up</button></form>
                                <form method="post" action="{{ route('tenant.cms.sections.move',[$section,'down']) }}">@csrf<button class="button button-ghost">↓ Down</button></form>
                            </div>
                        </div>

                        <form method="post" action="{{ route('tenant.builder.sections.update',$section) }}" class="form-stack" style="margin-top:18px">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid">
                                <label>Editor name<input name="name" maxlength="120" value="{{ $section->name }}"></label>
                                <label>Eyebrow<input name="eyebrow" maxlength="100" value="{{ $content['eyebrow'] ?? '' }}"></label>
                                <label>Heading<input name="heading" maxlength="255" value="{{ $content['heading'] ?? '' }}"></label>
                                <label>Button label<input name="button_label" maxlength="100" value="{{ $content['button_label'] ?? ($content['primary_label'] ?? '') }}"></label>
                                <label>Button URL<input name="button_url" maxlength="500" value="{{ $content['button_url'] ?? ($content['primary_url'] ?? '') }}" placeholder="/events"></label>
                                <label>Image path / URL<input name="image_url" maxlength="1000" value="{{ $content['image_url'] ?? '' }}"></label>
                                <label>Image alt text<input name="image_alt" maxlength="255" value="{{ $content['image_alt'] ?? '' }}"></label>
                                <label>Layout<select name="layout"><option value="default" @selected(($settings['layout']??'default')==='default')>Default</option><option value="centered" @selected(($settings['layout']??'')==='centered')>Centered</option><option value="split" @selected(($settings['layout']??'')==='split')>Split</option><option value="cards" @selected(($settings['layout']??'')==='cards')>Cards</option><option value="grid" @selected(($settings['layout']??'')==='grid')>Grid</option></select></label>
                                <label>Background<select name="background"><option value="default" @selected(($settings['background']??'default')==='default')>Default</option><option value="muted" @selected(($settings['background']??'')==='muted')>Muted</option><option value="accent" @selected(($settings['background']??'')==='accent')>Accent</option></select></label>
                                <label>Columns<input name="columns" type="number" min="1" max="4" value="{{ $settings['columns'] ?? 3 }}"></label>
                            </div>
                            <label>Body<textarea name="body" rows="5" maxlength="10000">{{ $content['body'] ?? '' }}</textarea></label>

                            @if(in_array($section->type,['faq','testimonials','stats','gallery'],true))
                                <label>Items<textarea name="items" rows="7" maxlength="20000">{{ $itemLines }}</textarea>
                                    <small>
                                        @if($section->type==='faq')Question | Answer, one per line.@endif
                                        @if($section->type==='testimonials')Quote | Name | Detail, one per line.@endif
                                        @if($section->type==='stats')Value | Label, one per line.@endif
                                        @if($section->type==='gallery')Image URL | Alt text | Caption, one per line. Unsafe URL schemes are rejected.@endif
                                    </small>
                                </label>
                            @endif

                            @if($section->type==='contact')
                                <div class="pg-privacy-note">The public form requires name, email, message and an explicit privacy acknowledgement. Form entries are operational inquiries and are never treated as marketing consent.</div>
                            @endif

                            <label class="row-actions"><input type="checkbox" name="is_enabled" value="1" @checked($section->is_enabled)> Show this section</label>
                            <button class="button button-primary">Save section</button>
                        </form>

                        <div class="pg-actions" style="margin-top:16px">
                            <form method="post" action="{{ route('tenant.builder.reusable.store',$section) }}" class="form-inline">@csrf<input name="name" maxlength="120" required placeholder="Reusable block name"><button class="button button-ghost">Save reusable copy</button></form>
                            <form method="post" action="{{ route('tenant.cms.sections.destroy',$section) }}" onsubmit="return confirm('Delete this section from the page?')">@csrf @method('DELETE')<button class="button button-ghost">Delete section</button></form>
                        </div>
                    </article>
                @empty
                    <div class="pg-card"><h3>This page has no sections</h3><p class="muted">Add a section above or insert a reusable block.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</section>
@endsection
