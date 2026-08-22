@extends('layouts.app')
@section('title',$tenant?'Join '.$tenant->name:'Join Private Gather')
@section('content')
<section class="auth-shell pg-lifestyle-signup"><form method="post" action="{{route('register.store')}}" class="panel form-card pg-signup-card">@csrf
<div class="eyebrow">{{ $tenant ? 'CLUB MEMBERSHIP APPLICATION' : 'PRIVATE GATHER MEMBER PROFILE' }}</div>
<h1>{{ $tenant ? 'Join '.$tenant->name : 'Create your Private Gather account' }}</h1>
<p>{{ $tenant ? 'Create your account and lifestyle profile. This club reviews new-member applications before private community access is granted.' : 'Create an adult lifestyle profile you can use with Private Gather clubs and communities.' }}</p>
<div class="pg-privacy-callout"><strong>Privacy first.</strong> Your legal name, email, date of birth and application details are never displayed as your public member identity. Your username is what other members see. Lifestyle fields have member-level privacy controls after signup.</div>

<h2>Account</h2><div class="form-grid"><label>Legal name <small>Private account record</small><input name="name" value="{{old('name')}}" required></label><label>Username <small>3–32 characters; letters, numbers, dot, underscore or hyphen</small><input name="username" value="{{old('username')}}" minlength="3" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,31}" autocomplete="username" required></label><label>Email<input type="email" name="email" value="{{old('email')}}" required></label><label>Date of birth <small>18+ verification; hidden by default</small><input type="date" name="date_of_birth" value="{{old('date_of_birth')}}" required></label><label>Password<input type="password" name="password" required></label><label>Confirm password<input type="password" name="password_confirmation" required></label></div>

<h2>Lifestyle profile</h2><div class="form-grid"><label>Profile / member type<select name="lifestyle_identity" required><option value="">Choose one</option>@foreach($lifestyleIdentities as $value=>$label)<option value="{{$value}}" @selected(old('lifestyle_identity')===$value)>{{$label}}</option>@endforeach</select></label><label>Relationship status<select name="relationship_status"><option value="">Optional</option>@foreach($relationshipStatuses as $value=>$label)<option value="{{$value}}" @selected(old('relationship_status')===$value)>{{$label}}</option>@endforeach</select></label><label>Experience level<select name="experience_level"><option value="">Optional</option>@foreach($experienceLevels as $value=>$label)<option value="{{$value}}" @selected(old('experience_level')===$value)>{{$label}}</option>@endforeach</select></label><label>Pronouns <small>Optional</small><input name="pronouns" value="{{old('pronouns')}}" maxlength="80"></label><label>City<input name="city" value="{{old('city')}}" maxlength="120"></label><label>State<select name="region"><option value="">Choose a state</option>@foreach($usStates as $value=>$label)<option value="{{$value}}" @selected(old('region')===$value)>{{$label}}</option>@endforeach</select></label><label class="span2">Profile headline<input name="headline" value="{{old('headline')}}" maxlength="160" placeholder="Friendly couple who loves social events and travel"></label></div>

<div class="pg-option-section"><h3>Who would you like to connect with?</h3><p class="muted">Choose any that apply. You can change these later.</p><div class="pg-choice-grid">@foreach($lookingForOptions as $value=>$label)<label class="pg-choice"><input type="checkbox" name="looking_for[]" value="{{$value}}" @checked(in_array($value,(array)old('looking_for',[]),true))><span>{{$label}}</span></label>@endforeach</div></div>

<div class="pg-option-section"><h3>Lifestyle interests</h3><p class="muted">Optional compatibility information for the private member community. This is never shown with your legal account information.</p><div class="pg-choice-grid">@foreach($lifestyleInterestOptions as $value=>$label)<label class="pg-choice"><input type="checkbox" name="lifestyle_interests[]" value="{{$value}}" @checked(in_array($value,(array)old('lifestyle_interests',[]),true))><span>{{$label}}</span></label>@endforeach</div></div>

<label>Boundaries / comfort notes <small>Optional. Defaults to connections-only visibility after signup.</small><textarea name="boundaries" rows="3" maxlength="2500" placeholder="Share only what you are comfortable having approved connections know.">{{old('boundaries')}}</textarea></label>
@if($tenant)<label>Anything you want the club to know about your application? <small>Visible only to club membership staff.</small><textarea name="application_note" rows="3" maxlength="3000">{{old('application_note')}}</textarea></label>@endif

<label class="check"><input type="checkbox" name="adult" required> I confirm I am at least 18 years old.</label>
<label class="check"><input type="checkbox" name="consent_culture" required> I agree to respect consent, boundaries, privacy, and each member’s right to decline any interaction.</label>
<label class="check"><input type="checkbox" name="terms" required> I agree to the terms and community rules.</label>
<label class="check"><input type="checkbox" name="privacy" required> I acknowledge the privacy policy and data practices.</label>
@if($errors->any())<div class="error">{{$errors->first()}}</div>@endif
<button class="button button-primary button-large">{{ $tenant ? 'Submit Membership Application' : 'Create Account' }}</button><a href="{{route('login')}}">Already have an account?</a>
</form></section>
@endsection
