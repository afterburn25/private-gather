<?php

declare(strict_types=1);

function installer_base_path(): string
{
    return dirname(__DIR__);
}

function installer_lock_path(): string
{
    return installer_base_path().'/storage/app/installed.lock';
}

function installer_is_installed(): bool
{
    if (is_file(installer_lock_path())) {
        return true;
    }

    $env = installer_base_path().'/.env';
    if (! is_file($env)) {
        return false;
    }

    $contents = (string) @file_get_contents($env);
    return preg_match('/^APP_INSTALLED\s*=\s*(true|1|yes|on)\s*$/mi', $contents) === 1;
}

function installer_normalize_host(string $host): string
{
    $host = strtolower(trim($host));
    if (str_starts_with($host, '[')) {
        $end = strpos($host, ']');
        return $end === false ? trim($host, '[]') : trim(substr($host, 1, $end - 1));
    }

    return preg_replace('/:\d+$/', '', $host) ?? $host;
}

function installer_request_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $installPos = strpos($script, '/install/');
    if ($installPos !== false) {
        return rtrim(substr($script, 0, $installPos), '/');
    }

    $dir = str_replace('\\', '/', dirname($script));
    return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
}

function installer_detect_url(): string
{
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http').'://'.$host.installer_request_base_path();
}

function installer_app_href(): string
{
    $base = installer_request_base_path();
    return $base === '' ? '/' : $base.'/';
}

function installer_asset_href(string $relative): string
{
    $base = installer_request_base_path();
    return ($base === '' ? '' : $base).'/'.ltrim($relative, '/');
}

function installer_requirements(): array
{
    $base = installer_base_path();
    $requirements = [
        ['PHP 8.3+', version_compare(PHP_VERSION, '8.3.0', '>=')],
        ['PDO MySQL', extension_loaded('pdo_mysql')],
        ['Mbstring', extension_loaded('mbstring')],
        ['OpenSSL', extension_loaded('openssl')],
        ['Fileinfo', extension_loaded('fileinfo')],
        ['JSON', extension_loaded('json')],
        ['Storage writable', is_dir($base.'/storage') && is_writable($base.'/storage')],
        ['Bootstrap cache writable', is_dir($base.'/bootstrap/cache') && is_writable($base.'/bootstrap/cache')],
        ['Composer dependencies installed', is_file($base.'/vendor/autoload.php')],
    ];

    return $requirements;
}

function installer_requirements_pass(array $requirements): bool
{
    foreach ($requirements as $requirement) {
        if (! ($requirement[1] ?? false)) {
            return false;
        }
    }
    return true;
}

function installer_connect_database(array $input): PDO
{
    $host = trim((string) ($input['db_host'] ?? 'localhost')) ?: 'localhost';
    $port = (int) ($input['db_port'] ?? 3306);
    $database = trim((string) ($input['db_database'] ?? ''));
    $username = (string) ($input['db_username'] ?? '');
    $password = (string) ($input['db_password'] ?? '');

    if (! preg_match('/^[A-Za-z0-9_$-]+$/', $database)) {
        throw new InvalidArgumentException('Database name contains unsupported characters.');
    }
    if ($port < 1 || $port > 65535) {
        throw new InvalidArgumentException('Database port is invalid.');
    }

    $serverDsn = 'mysql:host='.$host.';port='.$port.';charset=utf8mb4';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];

    if (($input['db_create'] ?? '') === '1') {
        $server = new PDO($serverDsn, $username, $password, $options);
        $server->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    $pdo = new PDO($serverDsn.';dbname='.$database, $username, $password, $options);
    installer_database_preflight($pdo);
    return $pdo;
}

function installer_database_preflight(PDO $pdo): void
{
    // Non-destructive privilege probe: temporary table lifecycle proves the
    // configured account can create/write/drop schema objects without touching
    // existing application tables.
    $probe = 'pg_install_probe_'.bin2hex(random_bytes(5));
    try {
        $pdo->exec('CREATE TEMPORARY TABLE `'.$probe.'` (id INT PRIMARY KEY, value VARCHAR(16) NOT NULL)');
        $stmt = $pdo->prepare('INSERT INTO `'.$probe.'` (id,value) VALUES (1,?)');
        $stmt->execute(['ok']);
        $value = $pdo->query('SELECT value FROM `'.$probe.'` WHERE id=1')->fetchColumn();
        if ($value !== 'ok') {
            throw new RuntimeException('Database privilege probe could not read its temporary test row.');
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Database connection succeeded, but the configured user cannot perform the schema operations required by Private Gather: '.$e->getMessage(), 0, $e);
    } finally {
        try {
            $pdo->exec('DROP TEMPORARY TABLE IF EXISTS `'.$probe.'`');
        } catch (Throwable) {
            // The original probe error is the actionable result.
        }
    }
}

function installer_env_quote(string $value): string
{
    if ($value === '' || preg_match('/[\s#="\'\\]/', $value)) {
        return '"'.addcslashes($value, "\\\"").'"';
    }
    return $value;
}

function installer_generate_key(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}

function installer_write_env(array $input): void
{
    $base = installer_base_path();
    $lines = [
        'APP_NAME='.installer_env_quote((string) $input['app_name']),
        'APP_ENV=production',
        'APP_KEY='.installer_env_quote(installer_generate_key()),
        'APP_DEBUG=false',
        'APP_URL='.installer_env_quote((string) $input['app_url']),
        'APP_TIMEZONE='.installer_env_quote((string) $input['timezone']),
        'APP_INSTALLED=true',
        '',
        'PRIVATE_GATHER_EDITION=hosted',
        'PLATFORM_ROOT_DOMAIN='.installer_env_quote((string) $input['platform_root_domain']),
        'SELF_HOSTED_REGISTRATION=approval',
        'SELF_HOSTED_VISIBILITY=private',
        '',
        'LOG_CHANNEL=stack',
        'LOG_LEVEL=warning',
        '',
        'DB_CONNECTION=mysql',
        'DB_HOST='.installer_env_quote((string) $input['db_host']),
        'DB_PORT='.(int) $input['db_port'],
        'DB_DATABASE='.installer_env_quote((string) $input['db_database']),
        'DB_USERNAME='.installer_env_quote((string) $input['db_username']),
        'DB_PASSWORD='.installer_env_quote((string) $input['db_password']),
        '',
        'SESSION_DRIVER=database',
        'SESSION_LIFETIME=120',
        'CACHE_STORE=database',
        'QUEUE_CONNECTION=database',
        '',
        'FILESYSTEM_DISK=local',
        'MAIL_MAILER=log',
    ];

    $payload = implode(PHP_EOL, $lines).PHP_EOL;
    $tmp = $base.'/.env.installing';
    if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write temporary environment file.');
    }
    @chmod($tmp, 0600);
    if (! @rename($tmp, $base.'/.env')) {
        @unlink($tmp);
        throw new RuntimeException('Unable to finalize .env file.');
    }
}

function installer_import_schema(PDO $pdo): void
{
    $schema = (string) file_get_contents(__DIR__.'/schema.sql');
    if ($schema === '') {
        throw new RuntimeException('Installer schema is missing.');
    }

    $schema = (string) preg_replace('/^\s*--.*$/m', '', $schema);

    $number = 0;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $number++;
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Database schema import failed at statement '.$number.': '.$e->getMessage(),
                0,
                $e
            );
        }
    }
}

function installer_admin_username(PDO $pdo, string $name, string $email): string
{
    $seed = strtolower(trim((string) strstr($email, '@', true)));
    if ($seed === '') {
        $seed = strtolower($name);
    }
    $base = preg_replace('/[^a-z0-9._-]+/', '-', $seed) ?: '';
    $base = trim($base, '.-_');
    if (strlen($base) < 3) {
        $base = 'admin';
    }
    $base = substr($base, 0, 32);
    $candidate = $base;
    $counter = 1;
    $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? AND email<>?');

    while (true) {
        $exists->execute([$candidate, $email]);
        if ((int) $exists->fetchColumn() === 0) {
            return $candidate;
        }
        ++$counter;
        $suffix = '-'.$counter;
        $candidate = substr($base, 0, max(3, 32 - strlen($suffix))).$suffix;
    }
}

function installer_create_admin(PDO $pdo, array $input): int
{
    $email = strtolower(trim((string) $input['admin_email']));
    $name = trim((string) $input['admin_name']);
    $password = (string) $input['admin_password'];

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid administrator email address.');
    }
    if (mb_strlen($name) < 2) {
        throw new InvalidArgumentException('Administrator name is too short.');
    }
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Administrator password must be at least 12 characters.');
    }

    $username = installer_admin_username($pdo, $name, $email);
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare('INSERT INTO users (name, username, display_name, email, status, is_platform_admin, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), username=COALESCE(username,VALUES(username)), display_name=COALESCE(NULLIF(display_name,""),VALUES(display_name)), status="active", is_platform_admin=1, password=VALUES(password), updated_at=NOW()');
    $stmt->execute([$name, $username, $username, $email, 'active', $hash]);

    $lookup = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $lookup->execute([$email]);
    $id = (int) $lookup->fetchColumn();
    if ($id <= 0) {
        throw new RuntimeException('Administrator account was created but could not be resolved.');
    }

    return $id;
}

function installer_create_self_hosted_tenant(PDO $pdo, array $input, int $adminId): ?int
{
    if (($input['edition'] ?? 'hosted') !== 'self_hosted') {
        return null;
    }

    $name = trim((string) ($input['organization_name'] ?? $input['app_name'] ?? 'Private Gather'));
    if ($name === '') {
        throw new InvalidArgumentException('Organization name is required for Self-Hosted Edition.');
    }

    $type = (string) ($input['organization_type'] ?? 'private_host');
    if (! in_array($type, ['club', 'organizer', 'private_host'], true)) {
        throw new InvalidArgumentException('Invalid organization type.');
    }

    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'private-gather-site';
    $baseSlug = $slug;
    $suffix = 2;
    $exists = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug=?');
    while (true) {
        $exists->execute([$slug]);
        if ((int) $exists->fetchColumn() === 0) {
            break;
        }
        $slug = $baseSlug.'-'.$suffix++;
    }

    $settings = json_encode([
        'marketplace_enabled' => false,
        'self_hosted' => true,
        'privacy_mode' => (string) ($input['self_hosted_visibility'] ?? 'private'),
    ], JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare('INSERT INTO tenants (name,slug,type,status,plan,settings,created_at,updated_at) VALUES (?,?,?,"active","self_hosted",?,NOW(),NOW())');
    $stmt->execute([$name, $slug, $type, $settings]);
    $tenantId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO tenant_users (tenant_id,user_id,role,status,created_at,updated_at) VALUES (?, ?, "owner", "active", NOW(), NOW())');
    $stmt->execute([$tenantId, $adminId]);

    $host = installer_normalize_host((string) parse_url((string) $input['app_url'], PHP_URL_HOST));
    if ($host !== '') {
        $stmt = $pdo->prepare('INSERT INTO tenant_domains (tenant_id,domain,type,is_primary,status,verified_at,ssl_status,redirect_to_primary,dns_status,dns_last_checked_at,created_at,updated_at) VALUES (?, ?, "custom_domain", 1, "active", NOW(), "host_managed", 0, "active", NOW(), NOW(), NOW())');
        $stmt->execute([$tenantId, $host]);
    }

    $stmt = $pdo->prepare('INSERT INTO cms_pages (tenant_id,slug,title,status,is_homepage,published_at,created_at,updated_at) VALUES (?,"home","Home","published",1,NOW(),NOW(),NOW())');
    $stmt->execute([$tenantId]);
    $homeId = (int) $pdo->lastInsertId();

    $sections = [
        ['hero', 'Homepage Hero', 10, ['eyebrow' => 'WELCOME', 'heading' => $name, 'body' => 'Private events and member access for our community.', 'primary_label' => 'View Events', 'primary_url' => '/events']],
        ['event_grid', 'Upcoming Events', 20, ['heading' => 'Upcoming Events', 'limit' => 6]],
        ['cta', 'Member Access', 30, ['heading' => 'Member access', 'body' => 'Sign in to view private event information and manage your attendance.', 'button_label' => 'Member Login', 'button_url' => '/login']],
    ];
    $insertSection = $pdo->prepare('INSERT INTO cms_sections (cms_page_id,type,name,content,settings,sort_order,is_enabled,created_at,updated_at) VALUES (?,?,?,?,?, ?,1,NOW(),NOW())');
    foreach ($sections as [$typeName, $sectionName, $sort, $content]) {
        $insertSection->execute([$homeId, $typeName, $sectionName, json_encode($content, JSON_UNESCAPED_SLASHES), '{}', $sort]);
    }

    $stmt = $pdo->prepare('INSERT INTO cms_pages (tenant_id,slug,title,status,is_homepage,published_at,created_at,updated_at) VALUES (?,"about","About","published",0,NOW(),NOW(),NOW())');
    $stmt->execute([$tenantId]);
    $aboutId = (int) $pdo->lastInsertId();
    $insertSection->execute([$aboutId, 'rich_text', 'About Us', json_encode(['heading' => 'About '.$name, 'body' => 'Use Website & CMS to customize this private organization website.'], JSON_UNESCAPED_SLASHES), '{}', 10]);

    $setting = $pdo->prepare('INSERT INTO site_settings (tenant_id,`group`,`key`,value,type,is_public,created_at,updated_at) VALUES (?,"general",?,?,"string",1,NOW(),NOW())');
    $setting->execute([$tenantId, 'tagline', 'Private events and member access.']);
    $setting->execute([$tenantId, 'footer_text', 'Private Gather Self-Hosted · Events and member access managed on this installation.']);

    return $tenantId;
}

function installer_write_receipt(array $input): void
{
    $base = installer_base_path();
    $receipt = [
        'installed_at' => gmdate('c'),
        'app_url' => (string) $input['app_url'],
        'edition' => (string) ($input['edition'] ?? 'hosted'),
        'self_hosted_tenant_id' => $input['self_hosted_tenant_id'] ?? null,
        'self_hosted_visibility' => $input['self_hosted_visibility'] ?? null,
        'self_hosted_registration' => $input['self_hosted_registration'] ?? null,
        'platform_root_domain' => installer_normalize_host((string) $input['platform_root_domain']),
        'database' => (string) $input['db_database'],
        'installer_version' => is_file($base.'/VERSION') ? trim((string) file_get_contents($base.'/VERSION')) : '1.0.8',
    ];

    @file_put_contents($base.'/storage/app/install-record.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
    @file_put_contents(installer_lock_path(), 'installed '.gmdate('c').PHP_EOL, LOCK_EX);
}

function installer_remove_tree(string $path): bool
{
    if (! is_dir($path)) {
        return true;
    }

    $items = scandir($path);
    if ($items === false) {
        return false;
    }

    $ok = true;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $path.DIRECTORY_SEPARATOR.$item;
        if (is_dir($full) && ! is_link($full)) {
            $ok = installer_remove_tree($full) && $ok;
        } else {
            $ok = @unlink($full) && $ok;
        }
    }

    return @rmdir($path) && $ok;
}

function installer_disable_self(): bool
{
    $dir = __DIR__;
    if (installer_remove_tree($dir)) {
        return true;
    }

    $target = dirname($dir).'/.install-disabled-'.bin2hex(random_bytes(6));
    return @rename($dir, $target);
}
