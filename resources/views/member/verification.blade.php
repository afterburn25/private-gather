@extends('layouts.app')
@section('title','Age & Identity Verification')
@section('content')
<section class="shell section pg-suite-shell">
    <div class="pg-suite-pagehead"><div><span class="eyebrow">MEMBER TRUST</span><h1>Age & Identity Verification</h1><p>Private Gather uses a high-assurance verification provider to confirm age eligibility, government ID authenticity, and live-person/face-match checks without exposing your ID document to other members or clubs.</p></div><div class="row-actions"><a class="button button-ghost" href="{{route('dashboard')}}">Account</a><a class="button button-ghost" href="{{route('profile.edit')}}">Profile</a></div></div>
    @if(session('status'))<div class="notice">{{session('status')}}</div>@endif
    @if($errors->any())<div class="error">{{$errors->first()}}</div>@endif

    <section class="panel pg-member-trust-summary">
        <div class="panel-head"><div><span class="eyebrow">CURRENT STATUS</span><h2>{{$badge['label']}}</h2></div><span class="status-pill {{$badge['verified']?'status-ok':'status-pending'}}">{{$badge['verified']?'Verified':'Not fully verified'}}</span></div>
        <p>{{$badge['verified']?'Your verification is current and this profile may join additional clubs.':'An existing member profile must be fully verified before joining an additional club. Couple profiles require both linked partners to pass independently.'}}</p>
        <div class="pg-verification-privacy"><strong>Privacy:</strong> Private Gather stores the provider inquiry reference encrypted and a one-way lookup hash. The verification result, timestamps and audit metadata are retained; raw ID images and selfie biometrics are not stored by Private Gather.</div>
    </section>

    <div class="pg-verification-grid" style="margin-top:18px">
        @foreach($participants as $participant)
        <article class="panel pg-verification-participant">
            <div class="pg-verification-status"><div><span class="eyebrow">{{$participant['label']}}</span><h3>{{$participant['username']}}</h3></div><span class="status-pill {{$participant['verified']?'status-ok':'status-pending'}}">{{ucwords(str_replace('_',' ',$participant['status']))}}</span></div>
            @if($participant['verified_at'])<small>Verified {{$participant['verified_at']->format('M j, Y')}}@if($participant['expires_at']) · valid through {{$participant['expires_at']->format('M j, Y')}}@endif</small>@endif
            @if($participant['status']==='link_required')
                <p class="muted">Link the second partner account from Profile & Privacy before starting couple verification.</p>
                <a class="button button-ghost" href="{{route('profile.edit')}}">Link Partner Account</a>
            @elseif(!$participant['verified'])
                @if($configured)
                <form method="post" action="{{route('verification.start',$participant['slot'])}}">@csrf<button class="button button-primary">Start {{$participant['label']}} Verification</button></form>
                @else
                <p class="muted">Verification is not configured by the platform administrator yet.</p>
                @endif
            @endif
        </article>
        @endforeach
    </div>

    <section class="panel" style="margin-top:18px"><span class="eyebrow">WHAT IS CHECKED</span><h2>High-assurance member verification</h2><div class="pg-profile-facts"><div><span>Minimum configured age</span><strong>{{$minimumAge}}+</strong></div><div><span>Identity document</span><strong>Authenticity & expiration</strong></div><div><span>Person check</span><strong>Live selfie / liveness</strong></div><div><span>Identity match</span><strong>Face match</strong></div></div><p class="muted">A failed, declined, expired, or review result never creates a Verified Member badge. A completed approval expires after the platform's configured validity period and must then be renewed.</p></section>
</section>
@endsection
