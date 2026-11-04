<?php

declare(strict_types=1);

session_start();
require __DIR__.'/lib.php';
require __DIR__.'/community-schema.php';

if (installer_is_installed()) {
    http_response_code(410);
    header('Cache-Control: no-store');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Installer disabled</title></head><body style="font-family:system-ui;background:#111;color:#eee;padding:40px"><h1>Installer disabled</h1><p>This application is already installed. <a style="color:#d8b56a" href="'.h(installer_app_href()).'">Return to the site</a>.</p></body></html>';
    exit;
}

$checks = installer_requirements();
$csrf = $_SESSION['installer_csrf'] ??= bin2hex(random_bytes(32));
$errors = [];
$success = false;
$deleteOk = null;

$detectedUrl = installer_detect_url();
$detectedHost = installer_normalize_host((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

$defaults = [
    'edition' => 'hosted',
    'app_name' => 'Private Gather',
    'app_url' => $detectedUrl,
    'platform_root_domain' => $detectedHost,
    'organization_name' => '',
    'organization_type' => 'private_host',
    'self_hosted_visibility' => 'private',
    'self_hosted_registration' => 'approval',
    'timezone' => 'America/Chicago',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_database' => 'social_events',
    'db_username' => '',
    'db_password' => '',
    'db_create' => '1',
    'admin_name' => '',
    'admin_email' => '',
];
$data = array_merge($defaults, array_intersect_key($_POST, $defaults));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (! hash_equals($csrf, (string) ($_POST['_token'] ?? ''))) {
            throw new RuntimeException('Installer session expired. Refresh the page and try again.');
        }
        if (! installer_requirements_pass($checks)) {
            throw new RuntimeException('Server requirements are not satisfied. Resolve the failed checks before installing.');
        }

        $edition = strtolower(trim((string) ($_POST['edition'] ?? 'hosted')));
        if (! in_array($edition, ['hosted', 'self_hosted'], true)) {
            throw new InvalidArgumentException('Choose a valid Private Gather edition.');
        }
        if (trim((string) ($_POST['app_name'] ?? '')) === '') {
            throw new InvalidArgumentException('Site name is required.');
        }
        if (! filter_var((string) ($_POST['app_url'] ?? ''), FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Application URL is invalid.');
        }
        $rootDomain = installer_normalize_host((string) ($_POST['platform_root_domain'] ?? ''));
        if ($rootDomain === '' || ! preg_match('/^[a-z0-9.-]+$/', $rootDomain)) {
            throw new InvalidArgumentException('Platform/root domain is invalid.');
        }
        if ($edition === 'self_hosted' && trim((string) ($_POST['organization_name'] ?? '')) === '') {
            throw new InvalidArgumentException('Organization name is required for Self-Hosted Edition.');
        }
        if (! in_array((string) ($_POST['organization_type'] ?? ''), ['club', 'organizer', 'private_host'], true)) {
            throw new InvalidArgumentException('Choose a valid organization type.');
        }
        if (! in_array((string) ($_POST['self_hosted_visibility'] ?? ''), ['private', 'public'], true)) {
            throw new InvalidArgumentException('Choose a valid Self-Hosted visibility mode.');
        }
        if (! in_array((string) ($_POST['self_hosted_registration'] ?? ''), ['open', 'approval', 'disabled'], true)) {
            throw new InvalidArgumentException('Choose a valid Self-Hosted registration mode.');
        }
        if ((string) ($_POST['admin_password'] ?? '') !== (string) ($_POST['admin_password_confirm'] ?? '')) {
            throw new InvalidArgumentException('Administrator passwords do not match.');
        }

        $installInput = [
            'edition' => $edition,
            'app_name' => trim((string) $_POST['app_name']),
            'app_url' => rtrim((string) $_POST['app_url'], '/'),
            'platform_root_domain' => $rootDomain,
            'organization_name' => trim((string) ($_POST['organization_name'] ?? '')),
            'organization_type' => (string) ($_POST['organization_type'] ?? 'private_host'),
            'self_hosted_visibility' => (string) ($_POST['self_hosted_visibility'] ?? 'private'),
            'self_hosted_registration' => (string) ($_POST['self_hosted_registration'] ?? 'approval'),
            'timezone' => (string) $_POST['timezone'],
            'db_host' => trim((string) $_POST['db_host']),
            'db_port' => (int) $_POST['db_port'],
            'db_database' => trim((string) $_POST['db_database']),
            'db_username' => (string) $_POST['db_username'],
            'db_password' => (string) $_POST['db_password'],
            'db_create' => isset($_POST['db_create']) ? '1' : '',
            'admin_name' => trim((string) $_POST['admin_name']),
            'admin_email' => trim((string) $_POST['admin_email']),
            'admin_password' => (string) $_POST['admin_password'],
        ];

        $pdo = installer_connect_database($installInput);
        installer_import_schema($pdo);
        installer_import_private_community($pdo);

        $registeredMigrations = [
            '2026_08_18_021500_hash_existing_event_invitation_tokens',
            '2026_08_18_210000_create_private_community',
        ];
        $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
        foreach ($registeredMigrations as $migration) {
            $stmt->execute([$migration, $migration]);
        }

        $adminId = installer_create_admin($pdo, $installInput);
        $tenantId = installer_create_self_hosted_tenant($pdo, $installInput, $adminId);
        if ($tenantId !== null) {
            $installInput['self_hosted_tenant_id'] = $tenantId;
        }

        installer_write_env($installInput);
        installer_write_receipt($installInput);

        foreach (glob(installer_base_path().'/bootstrap/cache/*.php') ?: [] as $cacheFile) {
            @unlink($cacheFile);
        }

        $success = true;
        unset($_SESSION['installer_csrf']);
        session_write_close();
        $deleteOk = installer_disable_self();
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Private Gather Installer</title>
<style>
:root{color-scheme:dark;--bg:#0b0b0d;--panel:#15151a;--panel2:#1c1c22;--text:#f6f2e8;--muted:#a9a5a0;--gold:#d5b36a;--good:#6fd09a;--bad:#ff7c7c;--line:#2c2b31}*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top,#24201a 0,#0b0b0d 34%);color:var(--text)}.wrap{max-width:980px;margin:0 auto;padding:42px 20px 70px}.brand{font-weight:800;letter-spacing:.16em;color:var(--gold);font-size:13px}.installer-brand{display:flex;align-items:center;gap:14px}.installer-brand img{width:92px;height:auto;display:block;filter:drop-shadow(0 8px 24px rgba(213,179,106,.18))}.hero h1{font-size:clamp(34px,5vw,58px);margin:10px 0 8px}.hero p{color:var(--muted);max-width:760px;line-height:1.6}.card{background:rgba(21,21,26,.96);border:1px solid var(--line);border-radius:18px;padding:24px;margin-top:20px;box-shadow:0 22px 60px rgba(0,0,0,.24)}.card h2{margin:0 0 17px;font-size:20px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-size:13px;color:#d9d4ca;margin-bottom:7px;font-weight:650}input,select{width:100%;border:1px solid #35343c;background:#0f0f13;color:#fff;border-radius:10px;padding:12px 13px;outline:none}input:focus,select:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(213,179,106,.12)}.help{color:var(--muted);font-size:12px;margin-top:6px;line-height:1.45}.req{display:flex;justify-content:space-between;gap:20px;padding:10px 0;border-bottom:1px solid var(--line)}.req:last-child{border-bottom:0}.pass{color:var(--good)}.fail{color:var(--bad)}.alert{padding:14px 16px;border-radius:12px;margin-top:18px}.alert.bad{background:#351b1e;border:1px solid #663038;color:#ffd7d7}.alert.good{background:#163023;border:1px solid #28563e;color:#d9ffe9}.check{display:flex;gap:10px;align-items:flex-start}.check input{width:auto;margin-top:3px}.button{appearance:none;border:0;background:var(--gold);color:#14110c;font-weight:800;padding:14px 22px;border-radius:11px;cursor:pointer;font-size:15px}.button:disabled{opacity:.45;cursor:not-allowed}.footer{margin-top:25px;color:var(--muted);font-size:12px}.note{border-left:3px solid var(--gold);padding-left:13px;color:var(--muted);line-height:1.5}.edition-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.edition-card{border:1px solid var(--line);background:#101014;border-radius:14px;padding:16px}.edition-card strong{display:block;margin-bottom:5px}@media(max-width:700px){.grid,.edition-grid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{padding-top:25px}.card{padding:18px}}
</style>
</head>
<body><main class="wrap">
<div class="brand installer-brand"><img src="<?php echo h(installer_asset_href('assets/branding/private-gather-logo.png')); ?>" alt="Private Gather"><span>PRIVATE GATHER · UNIFIED INSTALLER</span></div>
<section class="hero"><h1>Install Private Gather</h1><p>One shared Private Gather core powers two editions. Choose Hosted for the multi-organization SaaS platform or Self-Hosted for one customer-owned private event site. Core features and updates remain synchronized between both editions.</p></section>

<?php if ($success): ?>
<div class="card">
  <div class="alert good"><strong>Installation complete.</strong> Private Gather <?php echo h($installInput['edition']==='self_hosted'?'Self-Hosted':'Hosted'); ?> Edition is configured and ready.</div>
  <p class="note"><?php echo $deleteOk ? 'The /install folder was removed automatically.' : 'The installer was locked, but this host prevented automatic folder removal/rename. Access is disabled; remove the install folder manually when convenient.'; ?></p>
  <p><a class="button" style="display:inline-block;text-decoration:none" href="<?php echo h(installer_app_href()); ?>">Open Private Gather</a></p>
</div>
<?php else: ?>
<?php foreach ($errors as $error): ?><div class="alert bad"><?php echo h($error); ?></div><?php endforeach; ?>

<form method="post" autocomplete="off">
<input type="hidden" name="_token" value="<?php echo h($csrf); ?>">
<div class="card"><h2>1. Choose Edition</h2>
<div class="grid"><div class="full"><label>Private Gather edition</label><select name="edition" id="edition"><option value="hosted" <?php echo $data['edition']==='hosted'?'selected':''; ?>>Hosted Edition — multi-organization SaaS</option><option value="self_hosted" <?php echo $data['edition']==='self_hosted'?'selected':''; ?>>Self-Hosted Edition — one private organization</option></select></div></div>
<div class="edition-grid" style="margin-top:14px"><div class="edition-card"><strong>Hosted Edition</strong><span class="help">Multiple clubs/organizers, Create Website, tenant isolation, hosted subdomains, marketplace and platform administration.</span></div><div class="edition-card"><strong>Self-Hosted Edition</strong><span class="help">One organization on the customer's own hosting. No wildcard subdomains or multi-site SaaS layer. Private/member-only access can be enforced.</span></div></div>
</div>

<div class="card"><h2>2. Server Requirements</h2>
<?php foreach ($checks as [$label,$ok]): ?><div class="req"><span><?php echo h($label); ?></span><strong class="<?php echo $ok?'pass':'fail'; ?>"><?php echo $ok?'PASS':'FAIL'; ?></strong></div><?php endforeach; ?>
<p class="help">The deployable package must include Composer's <code>vendor/</code> directory before the application can run.</p>
</div>

<div class="card"><h2>3. Website & Domain</h2><div class="grid">
<div class="full"><label>Application / brand name</label><input name="app_name" required value="<?php echo h((string)$data['app_name']); ?>"></div>
<div class="full"><label>Application URL</label><input name="app_url" required value="<?php echo h((string)$data['app_url']); ?>"><div class="help">For a subdirectory install include the full path, for example <strong>https://example.com/private-gather</strong>.</div></div>
<div><label>Root domain</label><input name="platform_root_domain" required value="<?php echo h((string)$data['platform_root_domain']); ?>"><div class="help">Hosted: root for customer subdomains. Self-Hosted: this installation's domain.</div></div>
<div><label>Timezone</label><select name="timezone"><option value="America/Chicago" selected>America/Chicago</option><option value="UTC">UTC</option><option value="America/New_York">America/New_York</option><option value="America/Denver">America/Denver</option><option value="America/Los_Angeles">America/Los_Angeles</option></select></div>
</div></div>

<div class="card"><h2>4. Self-Hosted Organization</h2><p class="help">These settings are used only when Self-Hosted Edition is selected. The installer creates this organization automatically and makes the first administrator its owner.</p><div class="grid">
<div class="full"><label>Organization / club name</label><input name="organization_name" value="<?php echo h((string)$data['organization_name']); ?>" placeholder="My Private Club"></div>
<div><label>Organization type</label><select name="organization_type"><option value="private_host" <?php echo $data['organization_type']==='private_host'?'selected':''; ?>>Private host</option><option value="club" <?php echo $data['organization_type']==='club'?'selected':''; ?>>Club / venue</option><option value="organizer" <?php echo $data['organization_type']==='organizer'?'selected':''; ?>>Event organizer</option></select></div>
<div><label>Website visibility</label><select name="self_hosted_visibility"><option value="private" <?php echo $data['self_hosted_visibility']==='private'?'selected':''; ?>>Private — login required</option><option value="public" <?php echo $data['self_hosted_visibility']==='public'?'selected':''; ?>>Public website</option></select></div>
<div><label>Member registration</label><select name="self_hosted_registration"><option value="approval" <?php echo $data['self_hosted_registration']==='approval'?'selected':''; ?>>Approval required</option><option value="open" <?php echo $data['self_hosted_registration']==='open'?'selected':''; ?>>Open registration</option><option value="disabled" <?php echo $data['self_hosted_registration']==='disabled'?'selected':''; ?>>Disabled</option></select></div>
</div></div>

<div class="card"><h2>5. Database</h2><div class="grid">
<div><label>Database host</label><input name="db_host" required value="<?php echo h((string)$data['db_host']); ?>"></div>
<div><label>Database port</label><input name="db_port" type="number" required value="<?php echo h((string)$data['db_port']); ?>"></div>
<div><label>Database name</label><input name="db_database" required value="<?php echo h((string)$data['db_database']); ?>"></div>
<div><label>Database username</label><input name="db_username" required value="<?php echo h((string)$data['db_username']); ?>"></div>
<div class="full"><label>Database password</label><input name="db_password" type="password" value=""></div>
<div class="full check"><input id="db_create" name="db_create" type="checkbox" value="1" <?php echo !empty($data['db_create'])?'checked':''; ?>><div><label for="db_create" style="margin:0">Create database automatically when permitted</label><div class="help">If your hosting account already created the database, Private Gather will use the named database.</div></div></div>
</div></div>

<div class="card"><h2>6. Administrator</h2><div class="grid">
<div><label>Name</label><input name="admin_name" required value="<?php echo h((string)$data['admin_name']); ?>"></div>
<div><label>Email</label><input name="admin_email" type="email" required value="<?php echo h((string)$data['admin_email']); ?>"></div>
<div><label>Password</label><input name="admin_password" type="password" required minlength="12"></div>
<div><label>Confirm password</label><input name="admin_password_confirm" type="password" required minlength="12"></div>
</div></div>

<div class="card"><h2>7. Install</h2><p class="note">The same Private Gather core is installed for both editions. Edition-specific capabilities are activated by configuration, so future shared features and security fixes can ship to Hosted and Self-Hosted together.</p><button class="button" type="submit" <?php echo installer_requirements_pass($checks)?'':'disabled'; ?>>Install Private Gather</button></div>
</form>
<?php endif; ?>
<div class="footer">Private Gather Unified Core · Hosted Edition + Self-Hosted Edition</div>
</main></body></html>