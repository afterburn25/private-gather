@extends('layouts.app')
@section('title','Membership')
@section('content')
<section class="container section narrow">
<div class="panel-head">
<div>
<div class="eyebrow">{{$tenant->isClub()?'CLUB MEMBERSHIP':'COMMUNITY MEMBERSHIP'}}</div>
<h1>{{ $accessMode === 'application' ? 'Apply to ' : 'Join ' }}{{$tenant->name}}</h1>
<p class="muted">Membership is for eligible adults only. This organization requires members to be at least {{$minimumAge}} and controls its own membership access without changing your standing in other Private Gather communities.</p>
</div>
<a class="btn" href="{{route('site.home')}}">Back to Site</a>
</div>

@if(session('status'))<div class="notice">{{session('status')}}</div>@endif
@if($errors->any())
<div class="alert alert-danger" role="alert"><strong>Please review your membership request.</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>
@endif

@if($activeMember)
<div class="panel">
<h2>Membership active</h2>
<p>You already have active membership with {{$tenant->name}}.</p>
</div>
@elseif($accessMode === 'invite_only')
<div class="panel">
<div class="eyebrow">INVITATION ONLY</div>
<h2>Membership requires an invitation</h2>
<p>{{$tenant->name}} is not accepting public membership applications. An authorized invitation or membership assignment from this organization is required.</p>
<p class="muted">Private Gather does not convert a normal account, email verification, or membership in another organization into access here.</p>
</div>
@elseif($accessMode === 'application' && $latestApplication && in_array($latestApplication->status,['pending','more_info','approved'],true))
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
<div class="eyebrow">{{ $accessMode === 'open' ? 'OPEN MEMBERSHIP' : 'MEMBERSHIP APPLICATION' }}</div>
<h2>{{ $accessMode === 'open' ? 'Activate your membership' : 'Tell the membership team about you' }}</h2>
@if($accessMode === 'open')
<p class="muted">This organization allows eligible members to join immediately. Your eligibility and saved Private Gather profile type are rechecked when you submit.</p>
@endif

<label>Joining as
<select name="profile_type" required>
@forelse($allowedProfileTypes as $profileType)
<option value="{{$profileType}}" @selected(old('profile_type',auth()->user()?->profile?->profile_type ?: 'individual')===$profileType)>{{ucfirst($profileType)}}</option>
@empty
<option value="" disabled selected>No profile types are currently eligible</option>
@endforelse
</select>
</label>

@if($accessMode === 'application')
<label>Referred by <span class="muted">(optional)</span>
<input name="referred_by" value="{{old('referred_by')}}" maxlength="255" placeholder="Member name, event, or how you found the community">
</label>

<label>Introduction
<textarea name="introduction" rows="7" required minlength="20" maxlength="2500" placeholder="Introduce yourself or yourselves, what you're looking for in the community, and anything the membership team should know.">{{old('introduction')}}</textarea>
</label>
@endif

<label>
<input type="checkbox" name="rules_ack" value="1" required @checked(old('rules_ack'))>
I have reviewed and agree to follow {{$tenant->isClub()?'the house rules':'the community standards'}}, including affirmative consent and respectful-boundary requirements.
</label>

<label>
<input type="checkbox" name="privacy_ack" value="1" required @checked(old('privacy_ack'))>
I understand that privacy and discretion apply to member identities, attendance, photos, conversations, and protected venue information.
</label>

<button class="btn primary" type="submit">{{ $accessMode === 'open' ? 'Activate Membership' : 'Submit Membership Application' }}</button>
</form>
@endif
</section>
@endsection
