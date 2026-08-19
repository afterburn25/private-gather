@extends('layouts.app')
@section('title','Security & Privacy')
@section('content')
<div class="container narrow">
    <h1>Security & Privacy</h1>
    @if(session('status'))<div class="notice">{{session('status')}}</div>@endif

    <div class="panel">
        <h2>Two-factor authentication</h2>
        @if($user->two_factor_confirmed_at)
            <p>Enabled since {{$user->two_factor_confirmed_at}}.</p>
            <form method="post" action="{{route('member.security.2fa.disable')}}">@csrf @method('delete')
                <input name="password" type="password" autocomplete="current-password" placeholder="Current password" required>
                <button>Disable 2FA</button>
            </form>
        @else
            <form method="post" action="{{route('member.security.2fa.begin')}}">@csrf
                <input name="password" type="password" autocomplete="current-password" placeholder="Current password" required>
                <button>Set Up Authenticator</button>
            </form>
        @endif
    </div>

    <div class="panel">
        <h2>Signed-in sessions</h2>
        <p class="muted">If you used Private Gather on another browser or device, you can invalidate those database sessions while keeping this browser signed in.</p>
        <form method="post" action="{{route('member.security.sessions.revoke')}}">@csrf
            <input name="password" type="password" autocomplete="current-password" placeholder="Current password" required>
            <button>Sign Out Other Sessions</button>
        </form>
    </div>

    <div class="panel">
        <h2>Recent security activity</h2>
        @forelse($securityEvents as $securityEvent)
            <div class="list-row">
                <div>
                    <strong>{{ucwords(str_replace(['.','_'], ' ', $securityEvent->event))}}</strong>
                    <small>{{$securityEvent->occurred_at?->format('M j, Y g:i A')}}</small>
                </div>
            </div>
        @empty
            <p class="muted">No security activity has been recorded yet.</p>
        @endforelse
    </div>

    <div class="panel">
        <h2>Your data</h2>
        <form method="post" action="{{route('member.security.data')}}">@csrf
            <select name="type"><option value="export">Request data export</option><option value="delete">Request account deletion</option></select>
            <input name="password" type="password" autocomplete="current-password" placeholder="Current password (required for deletion)">
            <small class="muted">A data export does not require your password. Account deletion requests require current-password confirmation.</small>
            <button>Submit Request</button>
        </form>
    </div>
</div>
@endsection