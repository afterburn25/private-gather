@extends('layouts.app')
@section('title','Membership Application')
@section('content')
<section class="container section narrow">
<div class="panel-head">
<div>
<div class="eyebrow">{{$tenant->isClub()?'CLUB MEMBERSHIP':'COMMUNITY MEMBERSHIP'}}</div>
<h1>Apply to {{$tenant->name}}</h1>
<p class="muted">Membership is for adults only. Applications are reviewed by this club or organization's own team and do not change your standing with other Private Gather communities.</p>
</div>
<a class="btn" href="{{route('site.home')}}">Back to Site</a>
</div>

@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
@if($errors->any())
<div class="alert alert-danger" role="alert"><strong>Please review your application.</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>
@endif

@if($activeMember)
<div class="panel">
<h2>Membership active</h2>
<p>You already have active membership with {{$tenant->name}}.</p>
</div>
@elseif($latestApplication && in_array($latestApplication->status,['pending','more_info','approved'],true))
<div class="panel">
<h2>Application status</h2>
<p><strong>{{ucwords(str_replace('_',' ',$latestApplication->status))}}</strong></p>
<p class="muted">Submitted {{$latestApplication->created_at->format('F j, Y')}}</p>
@if($latestApplication->decision_note)<p>{{$latestApplication->decision_note}}</p>@endif
@if($latestApplication->status==='more_info')<p class="muted">The membership team needs more information before making a decision. Contact the organization using its listed support method.</p>@endif
</div>
@else
<form method="post" action="{{route('membership.apply.store')}}" class="panel form-card">
@csrf
<h2>Tell the membership team about you</h2>

<label>Applying as
<select name="profile_type" required>
<option value="couple" @selected(old('profile_type',auth()->user()?->profile?->profile_type)==='couple')>Couple</option>
<option value="individual" @selected(old('profile_type',auth()->user()?->profile?->profile_type)!=='couple')>Individual</option>
</select>
</label>

<label>Referred by <span class="muted">(optional)</span>
<input name="referred_by" value="{{old('referred_by')}}" maxlength="255" placeholder="Member name, event, or how you found the community">
</label>

<label>Introduction
<textarea name="introduction" rows="7" required minlength="20" maxlength="2500" placeholder="Introduce yourself or yourselves, what you're looking for in the community, and anything the membership team should know.">{{old('introduction')}}</textarea>
</label>

<label>
<input type="checkbox" name="rules_ack" value="1" required @checked(old('rules_ack'))>
I have reviewed and agree to follow {{$tenant->isClub()?'the house rules':'the community standards'}}, including affirmative consent and respectful-boundary requirements.
</label>

<label>
<input type="checkbox" name="privacy_ack" value="1" required @checked(old('privacy_ack'))>
I understand that privacy and discretion apply to member identities, attendance, photos, conversations, and protected venue information.
</label>

<button class="btn primary" type="submit">Submit Membership Application</button>
</form>
@endif
</section>
@endsection
