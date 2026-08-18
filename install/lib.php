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

    return (bool) preg_match('/^APP_INSTALLED=(true|1|yes)$/mi', (string) @file_get_contents($env));
}

function installer_normalize_host(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('#^https?://#', '', $value) ?? $value;
    $value = explode('/', $value, 2)[0];
    $value = explode(':', $value, 2)[0];
    return trim($value, '.');
}

function installer_detect_base_uri(): string
{
    $appRoot = realpath(installer_base_path());
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/install/'), PHP_URL_PATH) ?: '/install/';
    $requestPath = '/'.ltrim(str_replace('\\', '/', $requestPath), '/');

    if (preg_match('#^(.+?)/install(?:/|$)#i', $requestPath, $m)) {
        return '/'.trim($m[1], '/');
    }

    if ($appRoot !== false) {
        $physicalName = basename(str_replace('\\', '/', $appRoot));
        if ($physicalName !== '') {
            $segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn ($v) => $v !== ''));
            foreach ($segments as $i => $segment) {
                if (strcasecmp(rawurldecode($segment), $physicalName) === 0) {
                    return '/'.implode('/', array_slice($segments, 0, $i + 1));
                }
            }
        }
    }

    foreach (['DOCUMENT_ROOT', 'CONTEXT_DOCUMENT_ROOT', 'ORIG_DOCUMENT_ROOT'] as $key) {
        $candidate = trim((string) ($_SERVER[$key] ?? ''));
        $docRoot = $candidate !== '' ? realpath($candidate) : false;

        if ($appRoot !== false && $docRoot !== false) {
            $app = rtrim(str_replace('\\', '/', $appRoot), '/');
            $doc = rtrim(str_replace('\\', '/', $docRoot), '/');

            if ($app !== $doc && str_starts_with($app.'/', $doc.'/')) {
                $relative = trim(substr($app, strlen($doc)), '/');
                if ($relative !== '') {
                    return '/'.$relative;
                }
            }
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $installDir = str_replace('\\', '/', dirname($script));
    $appDir = str_replace('\\', '/', dirname($installDir));
    if ($appDir !== '/' && $appDir !== '.' && $appDir !== '\\') {
        return '/'.trim($appDir, '/');
    }

    return '';
}

function installer_detect_url(): string
{
    $https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = installer_normalize_host((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme.'://'.$host.installer_detect_base_uri();
}

function installer_app_href(): string
{
    $base = installer_detect_base_uri();
    return ($base === '' ? '' : $base).'/';
}

function installer_asset_href(string $path): string
{
    $base = installer_detect_base_uri();
    return ($base === '' ? '' : $base).'/'.ltrim($path, '/');
}

function installer_requirements(): array
{
    $base = installer_base_path();
    $checks = [
        ['PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>=')],
        ['PDO extension', extension_loaded('pdo')],
        ['PDO MySQL extension', extension_loaded('pdo_mysql')],
        ['OpenSSL extension', extension_loaded('openssl')],
        ['Mbstring extension', extension_loaded('mbstring')],
        ['JSON extension', extension_loaded('json')],
        ['Fileinfo extension', extension_loaded('fileinfo')],
        ['Zip extension (required for backend upgrades)', extension_loaded('zip')],
        ['storage/ is writable', is_writable($base.'/storage')],
        ['bootstrap/cache/ is writable', is_writable($base.'/bootstrap/cache')],
        ['Project root is writable for .env', is_writable($base)],
        ['Laravel vendor dependencies are present', is_file($base.'/vendor/autoload.php')],
    ];

    return $checks;
}

function installer_requirements_pass(array $checks): bool
{
    foreach ($checks as $check) {
        if (! $check[1]) {
            return false;
        }
    }
    return true;
}

function installer_quote_env(string $value): string
{
    return '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', '\\n'], $value).'"';
}

function installer_write_env(array $input): void
{
    $base = installer_base_path();
    $appKey = 'base64:'.base64_encode(random_bytes(32));
    $appUrl = rtrim((string) $input['app_url'], '/');
    $rootDomain = installer_normalize_host((string) $input['platform_root_domain']);
    $mailHost = $rootDomain ?: 'example.test';
    $edition = (string) ($input['edition'] ?? 'hosted');
    $selfHosted = $edition === 'self_hosted';
    $tenantId = $selfHosted ? (string) ((int) ($input['self_hosted_tenant_id'] ?? 0)) : '';

    $lines = [
        'APP_NAME='.installer_quote_env((string) $input['app_name']),
        'APP_ENV=production',
        'APP_KEY='.$appKey,
        'APP_DEBUG=false',
        'APP_URL='.installer_quote_env($appUrl),
        'APP_TIMEZONE='.installer_quote_env((string) $input['timezone']),
        'APP_INSTALLED=true',
        'PRIVATE_GATHER_DOMAIN=privategather.com',
        'PRIVATE_GATHER_URL=https://privategather.com',
        'PRIVATE_GATHER_EDITION='.$edition,
        'SELF_HOSTED_TENANT_ID='.$tenantId,
        'SELF_HOSTED_VISIBILITY='.(string) ($input['self_hosted_visibility'] ?? 'private'),
        'SELF_HOSTED_REGISTRATION='.(string) ($input['self_hosted_registration'] ?? 'approval'),
        '',
        'PLATFORM_ROOT_DOMAIN='.$rootDomain,
        'PLATFORM_DOMAIN_TARGET=domains.'.$rootDomain,
        'PLATFORM_WILDCARD_ENABLED='.($selfHosted ? 'false' : 'true'),
        'PLATFORM_WILDCARD_TARGET='.$rootDomain,
        'PLATFORM_TENANT_SCHEME='.(parse_url($appUrl, PHP_URL_SCHEME) ?: 'https'),
        'PLATFORM_TENANT_MOUNT_PATH='.(string) (parse_url($appUrl, PHP_URL_PATH) ?: ''),
        'PLATFORM_RESERVED_SUBDOMAINS=www,admin,api,mail,support,help,billing,account,login,signup,status,cdn,assets,static,domains,install',
        'PLATFORM_TERMS_VERSION=1.0',
        'PLATFORM_PRIVACY_VERSION=1.0',
        '',
        'UPGRADE_PRODUCT=privategather/private-gather',
        'UPGRADE_MAX_UPLOAD_MB=256',
        'UPGRADE_KEEP_BACKUPS=5',
        'UPGRADE_REQUIRE_SIGNATURE=false',
        '',
        'LOG_CHANNEL=stack',
        'LOG_LEVEL=warning',
        '',
        'DB_CONNECTION=mysql',
        'DB_HOST='.installer_quote_env((string) $input['db_host']),
        'DB_PORT='.(int) $input['db_port'],
        'DB_DATABASE='.installer_quote_env((string) $input['db_database']),
        'DB_USERNAME='.installer_quote_env((string) $input['db_username']),
        'DB_PASSWORD='.installer_quote_env((string) $input['db_password']),
        '',
        'SESSION_DRIVER=database',
        'SESSION_LIFETIME=120',
        'SESSION_ENCRYPT=true',
        'SESSION_PATH=/',
        'SESSION_DOMAIN=',
        '',
        'CACHE_STORE=database',
        'QUEUE_CONNECTION=database',
        'FILESYSTEM_DISK=local',
        '',
        'MAIL_MAILER=log',
        'MAIL_FROM_ADDRESS='.installer_quote_env('noreply@'.$mailHost),
        'MAIL_FROM_NAME=${APP_NAME}',
        '',
    ];

    $tmp = $base.'/.env.installing';
    if (file_put_contents($tmp, implode(PHP_EOL, $lines), LOCK_EX) === false) {
        throw new RuntimeException('Unable to write temporary environment file.');
    }
    @chmod($tmp, 0600);

    if (! @rename($tmp, $base.'/.env')) {
        @unlink($tmp);
        throw new RuntimeException('Unable to activate .env. Check root folder permissions.');
    }
    @chmod($base.'/.env', 0600);
}

function installer_connect_database(array $input): PDO
{
    $host = (string) $input['db_host'];
    $port = (int) $input['db_port'];
    $database = (string) $input['db_database'];
    $username = (string) $input['db_username'];
    $password = (string) $input['db_password'];

    if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new InvalidArgumentException('Database name may contain only letters, numbers, and underscores.');
    }

    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if (! empty($input['db_create'])) {
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    return new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
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

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare('INSERT INTO users (name, display_name, email, status, is_platform_admin, password, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), display_name=VALUES(display_name), status="active", is_platform_admin=1, password=VALUES(password), updated_at=NOW()');
    $stmt->execute([$name, $name, $email, 'active', $hash]);

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
