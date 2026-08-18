<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$expect = static function (bool $ok, string $message) use (&$failures): void {
    if (! $ok) $failures[] = $message;
};

$rootIndex = (string) file_get_contents($root.'/index.php');
$frontController = (string) file_get_contents($root.'/private-gather.php');
$htaccess = (string) file_get_contents($root.'/.htaccess');
$installer = (string) file_get_contents($root.'/install/lib.php');
$installPage = (string) file_get_contents($root.'/install/index.php');

$expect(str_contains($rootIndex, "require __DIR__.'/private-gather.php'"), 'index.php does not delegate to the reliable controller');
$expect(! preg_match('/header\s*\(\s*[\'\"]Location:/i', $frontController), 'reliable controller still emits a Location redirect');
$expect(str_contains($frontController, "require \$base.'/install/index.php'"), 'inline installer include missing');
$expect(str_contains($frontController, 'private_gather_mount_path'), 'physical mount detector missing');
$expect(str_contains($frontController, "\$_SERVER['SCRIPT_NAME'] = \$canonicalScript"), 'installed-mode SCRIPT_NAME canonicalization missing');
$expect(str_contains($frontController, "\$_SERVER['PHP_SELF'] = \$canonicalScript"), 'installed-mode PHP_SELF canonicalization missing');
$expect(str_contains($frontController, "\$_SERVER['SCRIPT_FILENAME'] = \$base.'/index.php'"), 'installed-mode SCRIPT_FILENAME canonicalization missing');
$expect(str_contains($htaccess, 'DirectoryIndex private-gather.php index.php'), 'reliable controller is not first DirectoryIndex');
$expect(str_contains($htaccess, 'RewriteRule ^$ private-gather.php [QSA,L]'), 'application-root rewrite to reliable controller missing');
$expect(str_contains($htaccess, 'RewriteRule ^index\\.php$ private-gather.php [QSA,L,NC]'), 'explicit index rewrite to reliable controller missing');
$expect(str_contains($installer, 'installer_detect_base_uri'), 'installer base URI detector missing');
$expect(str_contains($installer, "return \$scheme.'://'.\$host.installer_detect_base_uri();"), 'installer APP_URL does not preserve subdirectory');
$expect(! str_contains($installPage, 'src="/assets/branding/private-gather-logo.png"'), 'installer logo still root-absolute');
$expect(! str_contains($installPage, 'href="/">Open Website'), 'installer completion link still root-absolute');
$expect(str_contains($htaccess, 'RewriteRule ^(assets|build|uploads|storage)/(.*)$ public/$1/$2'), 'subdirectory-safe public asset rewrite missing');
$expect(! str_contains($htaccess, '%{DOCUMENT_ROOT}/public/$1'), 'document-root-only public asset rule still present');

function expected_base_from_script(string $script): string {
    $dir = str_replace('\\', '/', dirname(str_replace('\\', '/', $script)));
    if ($dir === '/' || $dir === '.' || $dir === '\\') return '';
    return '/'.trim($dir, '/');
}
$expect(expected_base_from_script('/index.php') === '', 'root SCRIPT_NAME base detection failed');
$expect(expected_base_from_script('/private-gather/index.php') === '/private-gather', 'subdirectory SCRIPT_NAME base detection failed');

if ($failures) {
    fwrite(STDERR, "SUBDIRECTORY INSTALL VERIFY: FAIL\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "SUBDIRECTORY INSTALL VERIFY: PASS\n";
