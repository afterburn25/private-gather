@extends('layouts.app')
@section('title','Chapters & Local Groups — '.$tenant->name)
@section('content')
<section class="pg-page-hero"><div class="pg-shell">
<span class="pg-eyebrow">CLUB OS · ORGANIZATION STRUCTURE</span>
<h1>Chapters & local groups</h1>
<p>Create location-based chapters inside {{ $tenant->name }} without splitting members into disconnected websites. Chapters reuse Private Gather membership, moderation, posts and tenant privacy boundaries.</p>
<div class="pg-actions"><a class="button button-ghost" href="{{ route('tenant.dashboard') }}">Back to Club OS</a><a class="button button-ghost" href="{{ route('community.groups.index') }}">Member group directory</a></div>
</div></section>

<section class="pg-page"><div class="pg-shell">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Check the chapter details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="pg-section">
<div class="pg-section-head"><div><span class="pg-eyebrow">NEW CHAPTER</span><h2>Create a local chapter</h2><p>Use chapters for city groups, regional branches or recurring local communities under the parent organization.</p></div></div>
<form method="post" action="{{ route('tenant.chapters.store') }}" class="pg-card form-stack">@csrf
<div class="form-grid">
<label>Name<input name="name" maxlength="120" required value="{{ old('name') }}" placeholder="Chicago Chapter"></label>
<label>Chapter lead<select name="chapter_lead_user_id"><option value="">No assigned lead yet</option>@foreach($leadCandidates as $candidate)<option value="{{ $candidate->id }}" @selected((string)old('chapter_lead_user_id')===(string)$candidate->id)>{{ $candidate->display_name ?: $candidate->name }} · {{ $candidate->email }}</option>@endforeach</select></label>
<label>City<input name="city" maxlength="120" value="{{ old('city') }}" placeholder="Chicago"></label>
<label>State / Region<input name="region" maxlength="120" value="{{ old('region') }}" placeholder="Illinois"></label>
<label>Chapter visibility<select name="visibility"><option value="tenant" @selected(old('visibility','tenant')==='tenant')>Organization members only</option><option value="public" @selected(old('visibility')==='public')>Public chapter directory</option><option value="invite_only" @selected(old('visibility')==='invite_only')>Invite-only / hidden</option></select><small class="muted">Public exposes only the chapter name, location, description and lead on your branded site. Discussions still require organization membership.</small></label>
<label>Join policy<select name="join_policy"><option value="approval" @selected(old('join_policy','approval')==='approval')>Request approval</option><option value="open" @selected(old('join_policy')==='open')>Open to members</option><option value="invite_only" @selected(old('join_policy')==='invite_only')>Invite-only</option></select></label>
<label class="span2">Description<textarea name="description" rows="4" maxlength="4000" placeholder="Describe the local chapter, meeting area, member focus and participation expectations.">{{ old('description') }}</textarea></label>
<label class="row-actions span2"><input type="checkbox" name="allow_member_posts" value="1" @checked(old('allow_member_posts',true))> Chapter members may post in the chapter feed</label>
</div>
<button class="button button-primary" type="submit">Create chapter</button>
</form>
</section>

<section class="pg-section">
<div class="pg-section-head"><div><span class="pg-eyebrow">ORGANIZATION NETWORK</span><h2>Existing chapters</h2><p>Archiving removes a chapter from discovery without deleting its membership or discussion history. Public visibility never makes chapter discussions public.</p></div></div>
@if($chapters->isEmpty())
<div class="pg-card"><h3>No chapters yet</h3><p class="muted">Create the first chapter when this organization is ready to operate a city or regional group.</p></div>
@else
<div class="pg-grid pg-grid-2">
@foreach($chapters as $chapter)
<article class="pg-card">
<div class="pg-event-meta"><span class="pg-pill">Chapter</span><span class="pg-pill">{{ ucfirst($chapter->status) }}</span><span class="pg-pill">{{ $chapter->active_members_count }} members</span><span class="pg-pill">{{ $chapter->visibility === 'public' ? 'Public directory' : ($chapter->visibility === 'invite_only' ? 'Invite-only' : 'Members only') }}</span></div>
<h3 style="margin:.8rem 0 .25rem">{{ $chapter->name }}</h3>
@if($chapter->locationLabel())<p class="muted"><strong>{{ $chapter->locationLabel() }}</strong></p>@endif
<p class="muted">{{ $chapter->description ?: 'No chapter description yet.' }}</p>
<p class="muted">Lead: <strong>{{ $chapter->chapterLead?->display_name ?: $chapter->chapterLead?->name ?: 'Not assigned' }}</strong> · {{ str_replace('_',' ',ucfirst($chapter->join_policy)) }}</p>
@if($chapter->status==='active')<div class="pg-actions"><a class="button button-primary" href="{{ route('tenant.chapters.workspace',$chapter->id) }}">Chapter workspace</a><a class="button button-ghost" href="{{ route('chapters.show',$chapter->slug) }}" target="_blank" rel="noopener">Chapter page</a><a class="button button-ghost" href="{{ route('community.groups.show',$chapter) }}">Member discussion</a></div>@endif
<details style="margin-top:18px"><summary><strong>Edit chapter</strong></summary>
<form method="post" action="{{ route('tenant.chapters.update',$chapter->id) }}" class="form-stack" style="margin-top:14px">@csrf @method('PATCH')
<div class="form-grid">
<label>Name<input name="name" maxlength="120" required value="{{ $chapter->name }}"></label>
<label>Chapter lead<select name="chapter_lead_user_id"><option value="">No assigned lead</option>@foreach($leadCandidates as $candidate)<option value="{{ $candidate->id }}" @selected((int)$chapter->chapter_lead_user_id===(int)$candidate->id)>{{ $candidate->display_name ?: $candidate->name }} · {{ $candidate->email }}</option>@endforeach</select></label>
<label>City<input name="city" maxlength="120" value="{{ $chapter->city }}"></label>
<label>State / Region<input name="region" maxlength="120" value="{{ $chapter->region }}"></label>
<label>Chapter visibility<select name="visibility"><option value="tenant" @selected($chapter->visibility==='tenant')>Organization members only</option><option value="public" @selected($chapter->visibility==='public')>Public chapter directory</option><option value="invite_only" @selected($chapter->visibility==='invite_only')>Invite-only / hidden</option></select><small class="muted">Public directory visibility never exposes chapter posts or private member data.</small></label>
<label>Join policy<select name="join_policy"><option value="approval" @selected($chapter->join_policy==='approval')>Request approval</option><option value="open" @selected($chapter->join_policy==='open')>Open to members</option><option value="invite_only" @selected($chapter->join_policy==='invite_only')>Invite-only</option></select></label>
<label class="span2">Description<textarea name="description" rows="4" maxlength="4000">{{ $chapter->description }}</textarea></label>
<label class="row-actions span2"><input type="checkbox" name="allow_member_posts" value="1" @checked($chapter->allow_member_posts)> Chapter members may post</label>
</div>
<button class="button button-primary" type="submit">Save changes</button>
</form>
</details>
@if($chapter->status==='active')
<form method="post" action="{{ route('tenant.chapters.archive',$chapter->id) }}" style="margin-top:14px">@csrf @method('PATCH')<button class="button button-ghost" type="submit">Archive chapter</button></form>
@else
<form method="post" action="{{ route('tenant.chapters.restore',$chapter->id) }}" style="margin-top:14px">@csrf @method('PATCH')<button class="button button-ghost" type="submit">Restore chapter</button></form>
@endif
</article>
@endforeach
</div>
@endif
</section>
</div></section>
@endsection
