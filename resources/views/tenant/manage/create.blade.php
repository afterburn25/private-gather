@extends('layouts.app')
@section('title','Create Organization')
@section('content')
<section class="auth-shell">
<form method="post" action="{{ route('organizations.store') }}" class="panel form-card">
@csrf
<div class="eyebrow">CREATE A PRIVATE GATHER SITE</div>
<h1>Create your website</h1>
<p class="muted">Your site will be provisioned immediately with a starter homepage, About page, navigation, branding controls, and event tools.</p>
@if($errors->any())
<div class="alert alert-danger" role="alert">
<strong>We couldn't create the website yet.</strong>
<ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul>
</div>
@endif
<label>Name<input name="name" value="{{old('name')}}" required></label>
<label>Type<select name="type"><option value="club" @selected(old('type')==='club')>Club / venue</option><option value="organizer" @selected(old('type')==='organizer')>Event organizer</option><option value="private_host" @selected(old('type')==='private_host')>Private host</option></select></label>
<label>Free Private Gather address<div class="domain-input"><input name="subdomain" value="{{old('subdomain')}}" required placeholder="your-club"><span>.{{ config('platform.root_domain') }}</span></div><small class="muted">Spaces and capital letters are automatically converted to a web-safe address.</small></label>
<button class="btn primary" type="submit">Create Website</button>
</form>
</section>
@endsection
