<?php

declare(strict_types=1);

require dirname(__DIR__).'/install/database.php';

$auto = installer_database_dsn('localhost', null, 'private_gather');
if ($auto !== 'mysql:host=localhost;dbname=private_gather;charset=utf8mb4') {
    throw new RuntimeException('Auto localhost DSN is incorrect: '.$auto);
}
if (str_contains($auto, 'port=3306')) {
    throw new RuntimeException('Auto localhost mode must not force TCP port 3306.');
}

$explicit = installer_database_dsn('127.0.0.1', 3307, 'private_gather');
if (! str_contains($explicit, 'port=3307')) {
    throw new RuntimeException('Explicit database port was not preserved.');
}

if (installer_database_port(['db_port' => '']) !== null) {
    throw new RuntimeException('Blank database port must resolve to Auto/null.');
}
if (installer_database_port(['db_port' => '3307']) !== 3307) {
    throw new RuntimeException('Explicit database port was not parsed correctly.');
}

$installer = (string) file_get_contents(dirname(__DIR__).'/install/index.php');
if (! str_contains($installer, "'db_port' => ''")) {
    throw new RuntimeException('Installer no longer defaults database port to Auto/blank.');
}
if (! str_contains($installer, 'installer_connect_database_auto')) {
    throw new RuntimeException('Installer is not using automatic database transport selection.');
}
if (! str_contains($installer, 'installer_finalize_database_env')) {
    throw new RuntimeException('Installer is not preserving automatic database transport in .env.');
}
if (str_contains($installer, "'db_port' => '3306'")) {
    throw new RuntimeException('Installer regressed to a forced port 3306 default.');
}

$config = (string) file_get_contents(dirname(__DIR__).'/config/database.php');
if (! str_contains($config, "'port' => \$mysqlPort === '' ? null : \$mysqlPort")) {
    throw new RuntimeException('Laravel database config does not preserve blank/automatic port mode.');
}

$example = (string) file_get_contents(dirname(__DIR__).'/.env.example');
if (! str_contains($example, "DB_HOST=localhost\nDB_PORT=\n")) {
    throw new RuntimeException('.env.example does not default to localhost automatic transport.');
}

echo "INSTALLER DATABASE AUTO-TRANSPORT: PASS\n";
echo "localhost uses native MySQL transport unless an explicit port is entered.\n";
