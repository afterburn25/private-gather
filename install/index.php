<?php

declare(strict_types=1);

session_start();
require_once __DIR__.'/lib.php';
require_once __DIR__.'/community-schema.php';
require_once __DIR__.'/competitive-schema.php';
require_once __DIR__.'/hosted-member-network-schema.php';
require_once __DIR__.'/hosted-discovery-revenue-schema.php';
require_once __DIR__.'/lifestyle-community-suite-schema.php';

if (installer_is_installed()) {
    http_response_code(410);
    header('Cache-Control: no-store');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Installer disabled</title></head><body style="font-family:system-ui;background:#111;color:#eee;padding:40px"><h1>Installer disabled</h1><p>This Private Gather installation is already configured. <a style="color:#a78bfa" href="'.h(installer_app_href()).'">Return to Private Gather</a>.</p></body></html>';
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
    'app_name' => 'Private Gather',
    'app_url' => $detectedUrl,
    'platform_root_domain' => $detectedHost,
    'timezone' => 'America/Chicago',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_database' => '',
    'db_username' => '',
    'db_password' => '',
    'db_create' => '',
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
        if ((string) ($_POST['admin_password'] ?? '') !== (string) ($_POST['admin_password_confirm'] ?? '')) {
            throw new InvalidArgumentException('Administrator passwords do not match.');
        }

        // Private Gather is operated as one business platform. "hosted" remains
        // an internal compatibility flag only; there is no customer edition choice.
        $installInput = [
            'edition' => 'hosted',
            'app_name' => trim((string) $_POST['app_name']),
            'app_url' => rtrim((string) $_POST['app_url'], '/'),
            'platform_root_domain' => $rootDomain,
            'organization_name' => '',
            'organization_type' => 'club',
            'self_hosted_visibility' => 'private',
            'self_hosted_registration' => 'approval',
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
        installer_import_competitive_schema($pdo);
        installer_import_hosted_member_network($pdo);
        installer_import_hosted_discovery_revenue($pdo);
        installer_import_lifestyle_community_suite($pdo);

        // These migrations are represented by the fresh-install schemas above.
        // Registering them prevents Laravel from replaying schema already created.
        $registeredMigrations = [
            '2026_08_18_021500_hash_existing_event_invitation_tokens',
            '2026_08_18_210000_create_private_community',
            '2026_08_19_210000_create_membership_addons_promoters_marketing',
            '2026_08_19_220000_create_hosted_member_network',
            '2026_08_19_230000_create_hosted_member_matching',
            '2026_08_19_230000_create_hosted_club_directory_and_affiliate_revenue',
            '2026_08_22_030000_expand_lifestyle_community_suite',
            '2026_08_22_031000_create_community_poll_votes',
            '2026_08_22_032000_create_profile_partner_invites',
            '2026_08_22_033000_create_club_rewards',
            '2026_08_22_200000_global_username_and_age_verification_v123',
        ];
        $stmt = $pdo->prepare('INSERT INTO migrations (migration,batch) SELECT ?,1 WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration=?)');
        foreach ($registeredMigrations as $migration) {
            $stmt->execute([$migration, $migration]);
        }

        $adminId = installer_create_admin($pdo, $installInput);
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

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install Private Gather</title>
<style>
:root{color-scheme:dark;--bg:#09070f;--panel:#151020;--text:#f8f7fb;--muted:#aaa3ba;--purple:#8b5cf6;--purple2:#a78bfa;--good:#6fd09a;--bad:#ff7c7c;--line:rgba(167,139,250,.17)}*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 72% -8%,rgba(109,40,217,.22),transparent 34rem),var(--bg);color:var(--text)}.wrap{max-width:920px;margin:0 auto;padding:42px 20px 70px}.installer-brand{display:flex;align-items:center;gap:14px;font-weight:800;letter-spacing:.14em;color:var(--purple2);font-size:13px}.installer-brand img{width:86px;height:auto;display:block;filter:drop-shadow(0 8px 24px rgba(139,92,246,.25))}.hero h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(38px,6vw,66px);font-weight:500;letter-spacing:-.045em;margin:16px 0 8px}.hero p{color:var(--muted);max-width:760px;line-height:1.65}.card{background:linear-gradient(180deg,rgba(29,22,44,.92),rgba(17,12,26,.97));border:1px solid var(--line);border-radius:20px;padding:24px;margin-top:20px;box-shadow:0 24px 70px rgba(0,0,0,.26)}.card h2{margin:0 0 17px;font-size:20px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-size:13px;color:#ded8e7;margin-bottom:7px;font-weight:650}input,select{width:100%;border:1px solid rgba(167,139,250,.18);background:#0d0914;color:#fff;border-radius:10px;padding:12px 13px;outline:none}input:focus,select:focus{border-color:var(--purple);box-shadow:0 0 0 3px rgba(139,92,246,.12)}.help{color:var(--muted);font-size:12px;margin-top:6px;line-height:1.45}.req{display:flex;justify-content:space-between;gap:20px;padding:10px 0;border-bottom:1px solid var(--line)}.req:last-child{border-bottom:0}.pass{color:var(--good)}.fail{color:var(--bad)}.alert{padding:14px 16px;border-radius:12px;margin-top:18px}.alert.bad{background:#351b1e;border:1px solid #663038;color:#ffd7d7}.alert.good{background:#163023;border:1px solid #28563e;color:#d9ffe9}.check{display:flex;gap:10px;align-items:flex-start}.check input{width:auto;margin-top:3px}.button{appearance:none;border:0;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:white;font-weight:800;padding:14px 22px;border-radius:11px;cursor:pointer;font-size:15px;box-shadow:0 10px 32px rgba(109,40,217,.24)}.button:disabled{opacity:.45;cursor:not-allowed}.footer{margin-top:25px;color:var(--muted);font-size:12px}.note{border-left:3px solid var(--purple);padding-left:13px;color:var(--muted);line-height:1.5}@media(max-width:700px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{padding-top:25px}.card{padding:18px}}
</style>
<link rel="stylesheet" href="<?php echo h(installer_asset_href('install/assets/installer.css')); ?>?v=r7">
</head>
<body><main class="wrap">
<div class="installer-brand"><img width="86" src="<?php echo h(installer_asset_href('assets/branding/private-gather-logo.png')); ?>" alt="Private Gather"><span>PRIVATE GATHER · BUSINESS PLATFORM</span></div>
<section class="hero"><h1>Install Private Gather</h1><p>Configure the Private Gather platform that powers your marketplace, clubs, member community, events, ticketing, advertising and business operations. There are no software editions to choose from.</p></section>

<?php if ($success): ?>
<div class="card">
  <div class="alert good"><strong>Installation complete.</strong> Private Gather is configured and ready.</div>
  <p class="note"><?php echo $deleteOk ? 'The /install folder was removed automatically.' : 'The installer was permanently locked, but this host prevented automatic folder removal/rename. Remove the install folder manually when convenient.'; ?></p>
  <p><a class="button" style="display:inline-block;text-decoration:none" href="<?php echo h(installer_app_href()); ?>">Open Private Gather</a></p>
</div>
<?php else: ?>
<?php foreach ($errors as $error): ?><div class="alert bad"><?php echo h($error); ?></div><?php endforeach; ?>

<form method="post" autocomplete="off">
<input type="hidden" name="_token" value="<?php echo h($csrf); ?>">

<div class="card"><h2>1. Server Requirements</h2>
<?php foreach ($checks as [$label,$ok]): ?><div class="req"><span><?php echo h($label); ?></span><strong class="<?php echo $ok?'pass':'fail'; ?>"><?php echo $ok?'PASS':'FAIL'; ?></strong></div><?php endforeach; ?>
<p class="help">A full first-time deployment must include Composer dependencies. Normal future releases use the much smaller Private Gather Upgrade Overlay instead.</p>
</div>

<div class="card"><h2>2. Platform &amp; Domain</h2><div class="grid">
<div class="full"><label>Platform name</label><input name="app_name" required value="<?php echo h((string)$data['app_name']); ?>"></div>
<div class="full"><label>Application URL</label><input name="app_url" required value="<?php echo h((string)$data['app_url']); ?>"><div class="help">For your live business this will normally be <strong>https://privategather.com</strong>.</div></div>
<div><label>Root domain</label><input name="platform_root_domain" required value="<?php echo h((string)$data['platform_root_domain']); ?>"><div class="help">Used for Private Gather and club/organizer subdomains.</div></div>
<div><label>Timezone</label><select name="timezone"><option value="America/Chicago" <?php echo $data['timezone']==='America/Chicago'?'selected':''; ?>>America/Chicago</option><option value="UTC" <?php echo $data['timezone']==='UTC'?'selected':''; ?>>UTC</option><option value="America/New_York" <?php echo $data['timezone']==='America/New_York'?'selected':''; ?>>America/New_York</option><option value="America/Denver" <?php echo $data['timezone']==='America/Denver'?'selected':''; ?>>America/Denver</option><option value="America/Los_Angeles" <?php echo $data['timezone']==='America/Los_Angeles'?'selected':''; ?>>America/Los_Angeles</option></select></div>
</div></div>

<div class="card"><h2>3. Database</h2><div class="grid">
<div><label>Database host</label><input name="db_host" required value="<?php echo h((string)$data['db_host']); ?>"></div>
<div><label>Database port</label><input name="db_port" type="number" required value="<?php echo h((string)$data['db_port']); ?>"></div>
<div><label>Database name</label><input name="db_database" required value="<?php echo h((string)$data['db_database']); ?>"><div class="help">Use the exact database name shown by your hosting panel, including any account prefix.</div></div>
<div><label>Database username</label><input name="db_username" required value="<?php echo h((string)$data['db_username']); ?>"><div class="help">Use the exact MySQL/MariaDB username assigned to this database.</div></div>
<div class="full"><label>Database password</label><input name="db_password" type="password" value=""></div>
<div class="full check"><input id="db_create" name="db_create" type="checkbox" value="1" <?php echo !empty($data['db_create'])?'checked':''; ?>><div><label for="db_create" style="margin:0">Create database automatically when permitted</label><div class="help">Leave this OFF when your hosting panel already created the database.</div></div></div>
</div></div>

<div class="card"><h2>4. Platform Administrator</h2><div class="grid">
<div><label>Name</label><input name="admin_name" required value="<?php echo h((string)$data['admin_name']); ?>"></div>
<div><label>Email</label><input name="admin_email" type="email" required value="<?php echo h((string)$data['admin_email']); ?>"></div>
<div><label>Password</label><input name="admin_password" type="password" required minlength="12"></div>
<div><label>Confirm password</label><input name="admin_password_confirm" type="password" required minlength="12"></div>
</div></div>

<div class="card"><h2>5. Install</h2><p class="note">This creates your Private Gather business platform. After the initial installation, application releases should be installed from Admin → Upgrades using Private Gather Upgrade Overlay ZIPs, so you do not need to replace the entire application each time.</p><button class="button" type="submit" <?php echo installer_requirements_pass($checks)?'':'disabled'; ?>>Install Private Gather</button></div>
</form>
<?php endif; ?>
<div class="footer">Private Gather · Business Platform · privategather.com</div>
</main></body></html>