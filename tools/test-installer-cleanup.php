<?php

declare(strict_types=1);

require dirname(__DIR__).'/install/lib.php';
require dirname(__DIR__).'/install/cleanup.php';

$root = sys_get_temp_dir().'/private-gather-installer-cleanup-'.bin2hex(random_bytes(5));
$install = $root.'/install';
$fallback = $root.'/.install-disabled-test';

if (! mkdir($install.'/nested', 0755, true) || ! mkdir($fallback.'/nested', 0755, true)) {
    throw new RuntimeException('Unable to create cleanup test directories.');
}

file_put_contents($install.'/index.php', '<?php echo "installer";');
file_put_contents($install.'/nested/schema.sql', 'schema');
file_put_contents($fallback.'/index.php', '<?php echo "disabled";');
file_put_contents($fallback.'/nested/data.txt', 'data');
@chmod($install.'/index.php', 0444);
@chmod($install.'/nested/schema.sql', 0444);
@chmod($fallback.'/index.php', 0444);
@chmod($fallback.'/nested/data.txt', 0444);
@chmod($install.'/nested', 0555);
@chmod($install, 0555);
@chmod($fallback.'/nested', 0555);
@chmod($fallback, 0555);

try {
    if (! installer_cleanup_after_success($install)) {
        throw new RuntimeException('Installer cleanup reported failure.');
    }
    if (is_dir($install) || file_exists($install)) {
        throw new RuntimeException('/install still exists after cleanup.');
    }
    if (is_dir($fallback) || file_exists($fallback)) {
        throw new RuntimeException('Disabled installer fallback still exists after cleanup.');
    }

    echo "INSTALLER AUTO-DELETE: PASS\n";
} finally {
    if (is_dir($root)) {
        @chmod($root, 0775);
        @rmdir($root);
    }
}
