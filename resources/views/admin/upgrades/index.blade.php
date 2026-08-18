<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Center — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{\App\Support\MountUrl::to('/assets/app.css')}}">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <div class="container admin-topbar-inner">
        <div><span class="eyebrow">PLATFORM ADMIN</span><strong>{{ config('app.name') }}</strong></div>
        <div class="admin-top-actions"><span class="muted">v{{ $currentVersion }}</span><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="button button-ghost" type="submit">Sign out</button></form></div>
    </div>
</header>
<main class="admin-main container">
    <div class="admin-heading">
        <div><span class="eyebrow">SYSTEM</span><h1>Update Center</h1><p>Upload a Private Gather upgrade ZIP. The system validates it, creates backups, applies database migrations and installs the files automatically.</p></div>
        <div class="version-badge"><span>Installed</span><strong>{{ $currentVersion }}</strong></div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <section class="admin-grid-two">
        <div class="admin-card">
            <span class="eyebrow">INSTALL UPGRADE</span>
            <h2>Upload upgrade package</h2>
            <p class="muted">Maximum package size: {{ $maxUploadMb }} MB. Only packages created for Private Gather and your installed version are accepted.</p>
            <form method="post" action="{{ route('admin.upgrades.store') }}" enctype="multipart/form-data" class="upgrade-form">
                @csrf
                <label class="upload-drop">
                    <span>Upgrade ZIP</span>
                    <input type="file" name="upgrade" accept=".zip,application/zip" required>
                    <small>Select the release ZIP supplied for the next Private Gather version.</small>
                </label>
                <label class="checkbox-line"><input type="checkbox" name="confirm_backup" value="1" required> I understand the updater will place the site into maintenance mode and create a pre-upgrade backup.</label>
                <button class="button button-primary button-large" type="submit">Validate & Install Upgrade</button>
            </form>
        </div>
        <div class="admin-card">
            <span class="eyebrow">AUTOMATIC SAFETY</span>
            <h2>What happens automatically</h2>
            <ol class="upgrade-steps">
                <li>Validate product, version, manifest, paths and SHA-256 hashes.</li>
                <li>Stage the package outside the public web root.</li>
                <li>Back up files affected by the release and dump the database.</li>
                <li>Enable maintenance mode.</li>
                <li>Run the release's Laravel database migrations.</li>
                <li>Atomically replace or remove application files.</li>
                <li>Clear application caches and return the site online.</li>
            </ol>
            <div class="security-note">Release signatures: <strong>{{ $signatureRequired ? 'Required' : 'Ready but optional until a Privora Labs release key is provisioned' }}</strong></div>
        </div>
    </section>

    <section class="admin-card history-card">
        <div class="card-heading"><div><span class="eyebrow">HISTORY</span><h2>Upgrade history</h2></div></div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Started</th><th>Version</th><th>Status</th><th>Package</th><th>Recovery</th></tr></thead>
                <tbody>
                @forelse($history as $upgrade)
                    <tr>
                        <td>{{ $upgrade->started_at?->format('M j, Y g:i A') }}</td>
                        <td>{{ $upgrade->from_version }} → <strong>{{ $upgrade->to_version }}</strong></td>
                        <td><span class="status-pill status-{{ str_starts_with($upgrade->status, 'completed') ? 'ok' : (str_starts_with($upgrade->status, 'failed') ? 'bad' : 'pending') }}">{{ str_replace('_', ' ', $upgrade->status) }}</span></td>
                        <td><code>{{ \Illuminate\Support\Str::limit($upgrade->package_sha256, 16, '…') }}</code></td>
                        <td class="table-actions">
                            @if($upgrade->log_path)<a href="{{ route('admin.upgrades.log', $upgrade) }}">Log</a>@endif
                            @if($upgrade->backup_path)<a href="{{ route('admin.upgrades.backup', $upgrade) }}">Backup</a>@endif
                        </td>
                    </tr>
                    @if($upgrade->error_message)<tr class="error-row"><td colspan="5">{{ $upgrade->error_message }}</td></tr>@endif
                @empty
                    <tr><td colspan="5" class="muted">No upgrades have been installed yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
