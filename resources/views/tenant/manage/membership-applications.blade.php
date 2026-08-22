@extends('layouts.app')
@section('title','Membership Applications')
@section('content')
<section class="container section">
<div class="panel-head">
<div>
<div class="eyebrow">MEMBERSHIP & SCREENING</div>
<h1>Membership Applications</h1>
<p class="muted">Review prospective couples and individual members for {{$tenant->name}}. Decisions apply only to this club or organization and never change a person's global Private Gather account.</p>
</div>
<a class="btn" href="{{route('tenant.dashboard')}}">Back to Control Center</a>
</div>

@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif

<form method="get" action="{{route('tenant.membership-applications.index')}}" class="panel form-inline">
<input name="q" value="{{request('q')}}" placeholder="Search applicant name or email" aria-label="Search applicants">
<select name="status" aria-label="Application status">
<option value="">All statuses</option>
@foreach(['pending'=>'Pending','more_info'=>'Needs more info','approved'=>'Approved','declined'=>'Declined'] as $value=>$label)
<option value="{{$value}}" @selected(request('status')===$value)>{{$label}}</option>
@endforeach
</select>
<button type="submit">Filter</button>
@if(request('q')||request('status'))<a class="btn" href="{{route('tenant.membership-applications.index')}}">Clear</a>@endif
</form>

<div class="stack">
@forelse($applications as $application)
<article class="panel">
<div class="row-between">
<div>
<div class="eyebrow">{{strtoupper(str_replace('_',' ',$application->status))}}</div>
<h2>{{$application->user->display_name ?: $application->user->name}}</h2>
<p class="muted">{{$application->user->email}} · {{ucfirst($application->profile_type)}} application · Submitted {{$application->created_at->format('M j, Y g:i A')}}</p>
</div>
@if($application->reviewer)<span class="muted">Reviewed by {{$application->reviewer->display_name ?: $application->reviewer->name}}</span>@endif
</div>

@if($application->referred_by)<p><strong>Referred by:</strong> {{$application->referred_by}}</p>@endif
<div class="prose"><strong>Introduction</strong><br>{!!nl2br(e($application->introduction))!!}</div>
@if($application->decision_note)<p><strong>Review note:</strong> {{$application->decision_note}}</p>@endif

<form method="post" action="{{route('tenant.membership-applications.update',$application)}}" class="form-card">
@csrf
@method('patch')
<label>Decision
<select name="decision" required>
<option value="approved">Approve membership</option>
<option value="more_info" @selected($application->status==='more_info')>Request more information</option>
<option value="declined" @selected($application->status==='declined')>Decline application</option>
</select>
</label>
<label>Private review note <span class="muted">(optional)</span>
<textarea name="decision_note" rows="3" maxlength="2500" placeholder="Add a note about this decision.">{{$application->decision_note}}</textarea>
</label>
<button class="btn primary" type="submit">Save Decision</button>
</form>
</article>
@empty
<div class="panel empty-state">No membership applications match this view.</div>
@endforelse
</div>

{{$applications->links()}}
</section>
@endsection
