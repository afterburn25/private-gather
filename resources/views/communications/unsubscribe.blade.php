@extends('layouts.app')
@section('title','Communication Preferences')
@section('content')
<section class="pg-page"><div class="pg-shell" style="max-width:760px">
<div class="pg-card">
<span class="pg-eyebrow">COMMUNICATION PREFERENCES</span>
@if($success)
<h1>Marketing email unsubscribed</h1>
<p>You will no longer receive marketing email from {{ $tenantName }} at this address. Operational messages tied to an active membership, transaction, ticket or account may still be sent when necessary.</p>
@else
<h1>This unsubscribe link is unavailable</h1>
<p>The link is invalid, expired or has already been used. No communication preference was changed from this request.</p>
@endif
<a class="button button-ghost" href="{{ url('/') }}">Return to Private Gather</a>
</div>
</div></section>
@endsection
