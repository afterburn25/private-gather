@extends('layouts.app')
@section('title','Notifications')
@section('content')
<section class="shell section pg-suite-shell">
    <div class="pg-suite-pagehead"><div><span class="eyebrow">MEMBER CLIENT AREA</span><h1>Notifications</h1><p>Control community, message, connection and event alerts from one place.</p></div><div class="row-actions"><a class="button button-ghost" href="{{ route('dashboard') }}">Dashboard</a><form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="button button-primary">Mark All Read</button></form></div></div>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    <div class="pg-suite-2col">
        <main class="panel pg-notification-list">
            <div class="panel-head"><h2>Recent activity</h2><span class="muted">{{ $notifications->total() }} notifications</span></div>
            @forelse($notifications as $notification)
                @php($data=$notification->data??[])
                <article class="pg-notification {{ $notification->read_at?'':'is-unread' }}">
                    <div><span class="pg-notification-icon">{{ match($notification->type){'message'=>'✉','connection'=>'♡','reaction'=>'♥','comment'=>'◌','group_invite'=>'◎','membership'=>'✓',default=>'◇'} }}</span></div>
                    <div class="pg-notification-copy"><strong>{{ $data['title'] ?? ucfirst(str_replace('_',' ',$notification->type)) }}</strong><small>{{ $notification->created_at->diffForHumans() }}</small>@if(!empty($data['body']))<p>{{ $data['body'] }}</p>@endif<div class="row-actions">@if(!empty($data['url']))<a class="text-link" href="{{ $data['url'] }}">Open →</a>@endif @if(!$notification->read_at)<form method="post" action="{{ route('notifications.read',$notification) }}">@csrf @method('PATCH')<button class="btn">Mark read</button></form>@endif</div></div>
                </article>
            @empty<p class="muted">You do not have any notifications yet.</p>@endforelse
            {{ $notifications->links() }}
        </main>
        <aside class="panel pg-notification-prefs"><span class="eyebrow">PREFERENCES</span><h2>Choose what reaches you</h2><form class="form-stack" method="post" action="{{ route('notifications.preferences') }}">@csrf @method('PATCH')
            <h3>In-app</h3>
            @foreach(['in_app_messages'=>'Private messages','in_app_reactions'=>'Reactions & comments','in_app_connections'=>'Connections & group invitations','in_app_events'=>'Events & membership updates'] as $field=>$label)<label class="check"><input type="checkbox" name="{{ $field }}" value="1" @checked($preferences->{$field})> {{ $label }}</label>@endforeach
            <h3>Email</h3>
            @foreach(['email_messages'=>'Private messages','email_reactions'=>'Reactions & comments','email_connections'=>'Connections','email_group_activity'=>'Group activity','email_events'=>'Events','email_marketing'=>'Club marketing'] as $field=>$label)<label class="check"><input type="checkbox" name="{{ $field }}" value="1" @checked($preferences->{$field})> {{ $label }}</label>@endforeach
            <label class="check"><input type="checkbox" name="browser_notifications" value="1" @checked($preferences->browser_notifications)> Browser / PWA notifications when supported</label>
            <button class="button button-primary">Save Preferences</button>
        </form></aside>
    </div>
</section>
@endsection
