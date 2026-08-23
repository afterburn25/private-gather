<?php

declare(strict_types=1);

require dirname(__DIR__).'/install/database.php';

$auto = installer_database_candidates([
    'db_host' => 'localhost',
    'db_port' => '',
], 'private_gather');

if (count($auto) !== 2) {
    throw new RuntimeException('localhost Auto must expose exactly socket and forced TCP candidates.');
}
if (! str_contains((string) $auto[0]['dsn'], 'host=localhost') || str_contains((string) $auto[0]['dsn'], ';port=')) {
    throw new RuntimeException('localhost Auto first candidate must be native localhost without an explicit port.');
}
if (! str_contains((string) $auto[1]['dsn'], 'host=127.0.0.1;port=3306')) {
    throw new RuntimeException('localhost Auto second candidate must force TCP 127.0.0.1:3306.');
}

$explicit = installer_database_candidates([
    'db_host' => 'localhost',
    'db_port' => '3307',
], 'private_gather');
if (count($explicit) !== 1 || ! str_contains((string) $explicit[0]['dsn'], 'host=127.0.0.1;port=3307')) {
    throw new RuntimeException('An explicit port with localhost must force TCP through 127.0.0.1.');
}
if (str_contains((string) $explicit[0]['dsn'], 'host=localhost')) {
    throw new RuntimeException('Explicit localhost port still risks PDO socket semantics.');
}

$remote = installer_database_candidates([
    'db_host' => 'db.example.test',
    'db_port' => '',
], 'private_gather');
if (count($remote) !== 1 || ! str_contains((string) $remote[0]['dsn'], 'host=db.example.test') || str_contains((string) $remote[0]['dsn'], ';port=')) {
    throw new RuntimeException('Remote Auto host must preserve the supplied host without forcing a port.');
}

$input = ['db_host' => 'localhost', 'db_port' => ''];
installer_apply_database_transport($input, $auto[1]);
if (($input['db_host'] ?? null) !== '127.0.0.1' || ($input['db_port'] ?? null) !== '3306') {
    throw new RuntimeException('Successful TCP fallback is not persisted for the generated environment.');
}

$invalidCaught = false;
try {
    installer_database_port(['db_port' => '70000']);
} catch (InvalidArgumentException) {
    $invalidCaught = true;
}
if (! $invalidCaught) {
    throw new RuntimeException('Invalid database ports are not rejected.');
}

echo "INSTALLER DATABASE TRANSPORT AUTODETECT: PASS\n";
