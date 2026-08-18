<?php

declare(strict_types=1);

/*
 * Private Gather reliable front controller.
 *
 * This entry deliberately does not depend on Apache DirectoryIndex behavior.
 * The application .htaccess rewrites the application-root request here before
 * Apache's real-directory bypass, matching the proven subdirectory strategy
 * used by the working Model Hub deployment.
 */

$base = __DIR__;
$lock = $base.'/storage/app/installed.lock';
$env = $base.'/.env';

$installed = is_file($lock);
if (! $installed && is_file($env)) {
    $envText = (string) @file_get_contents($env);
    $installed = (bool) preg_match('/^APP_INSTALLED=(true|1|yes)$/mi', $envText);
}

/** Resolve the application's browser mount from its physical relationship to the document root. */
function private_gather_mount_path(string $base): string
{
    $appRoot = realpath($base);
    foreach (['DOCUMENT_ROOT', 'CONTEXT_DOCUMENT_ROOT', 'ORIG_DOCUMENT_ROOT'] as $key) {
        $candidate = trim((string) ($_SERVER[$key] ?? ''));
        $docRoot = $candidate !== '' ? realpath($candidate) : false;
        if ($appRoot === false || $docRoot === false) {
            continue;
        }

        $app = rtrim(str_replace('\\', '/', $appRoot), '/');
        $doc = rtrim(str_replace('\\', '/', $docRoot), '/');
        if ($app === $doc) {
            return '';
        }
        if (str_starts_with($app.'/', $doc.'/')) {
            $relative = trim(substr($app, strlen($doc)), '/');
            if ($relative !== '') {
                return '/'.$relative;
            }
        }
    }

    // Managed-host fallback: use the executing front controller's directory.
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = trim(str_replace('\\', '/', dirname($script)), '/.');
    return $dir === '' ? '' : '/'.$dir;
}

$mount = private_gather_mount_path($base);
header('X-Private-Gather-Entry: reliable-front-controller-107');

if (! $installed) {
    // Render the installer at the URL the visitor requested. There is no
    // Location header and no browser redirect to /install/.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Private-Gather-Installer: inline');
    require $base.'/install/index.php';
    exit;
}

/*
 * Present a stable canonical script name to Laravel. Internal Apache rewrites
 * may execute private-gather.php, but generated application URLs must retain
 * the real application mount (e.g. /private-gather), never expose this helper
 * filename and never collapse to the domain root.
 */
$canonicalScript = ($mount === '' ? '' : $mount).'/index.php';
$_SERVER['SCRIPT_NAME'] = $canonicalScript;
$_SERVER['PHP_SELF'] = $canonicalScript;

// Rewrite-independent compatibility route, modeled after the working Model Hub
// fallback. This is diagnostic/fallback only; normal visitors keep clean URLs.
if (isset($_GET['route']) && is_string($_GET['route'])) {
    $route = '/'.ltrim($_GET['route'], '/');
    if ($route === '//') {
        $route = '/';
    }
    if (! preg_match('#^/[A-Za-z0-9_./~-]*$#', $route) || str_contains($route, '..')) {
        http_response_code(400);
        exit('Invalid route.');
    }

    $query = $_GET;
    unset($query['route']);
    $uri = ($mount === '' ? '' : $mount).($route === '/' ? '/' : $route);
    if ($query !== []) {
        $uri .= '?'.http_build_query($query);
    }
    $_SERVER['REQUEST_URI'] = $uri;
}

require $base.'/public/index.php';
