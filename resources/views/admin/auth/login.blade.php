<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Private Gather Administration</title>
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/app.css')}}">
</head>
<body>
<main class="admin-login-shell">
    <form class="admin-login-card" method="post" action="{{ route('admin.login.store') }}">
        @csrf
        <img class="admin-brand-logo" src="{{\App\Support\MountUrl::to('/assets/branding/private-gather-logo.png')}}" alt="Private Gather"><span class="eyebrow">PRIVATE GATHER ADMINISTRATION</span>
        <h1>Administrator sign in</h1>
        <p class="muted">Use the Private Gather administrator account created during browser installation.</p>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <label class="checkbox-line"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="button button-primary button-large" type="submit">Sign In</button>
    </form>
</main>
</body>
</html>
