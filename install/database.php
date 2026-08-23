<?php

declare(strict_types=1);

/**
 * Return an explicit port only when the operator supplied one.
 * Blank means PDO/MySQL should use its native transport; with localhost this
 * normally selects the configured Unix socket instead of forcing TCP 3306.
 */
function installer_database_port(array $input): ?int
{
    $raw = trim((string) ($input['db_port'] ?? ''));
    if ($raw === '') {
        return null;
    }

    if (! ctype_digit($raw)) {
        throw new InvalidArgumentException('Database port must be blank for Auto or a number between 1 and 65535.');
    }

    $port = (int) $raw;
    if ($port < 1 || $port > 65535) {
        throw new InvalidArgumentException('Database port must be blank for Auto or a number between 1 and 65535.');
    }

    return $port;
}

function installer_database_dsn(string $host, ?int $port, ?string $database = null): string
{
    $dsn = 'mysql:host='.$host;
    if ($port !== null) {
        $dsn .= ';port='.$port;
    }
    if ($database !== null && $database !== '') {
        $dsn .= ';dbname='.$database;
    }
    return $dsn.';charset=utf8mb4';
}

function installer_database_pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

function installer_database_exception(Throwable $e, array $input): RuntimeException
{
    $driverCode = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;
    $host = trim((string) ($input['db_host'] ?? 'localhost')) ?: 'localhost';
    $port = trim((string) ($input['db_port'] ?? ''));
    $username = trim((string) ($input['db_username'] ?? ''));
    $database = trim((string) ($input['db_database'] ?? ''));

    if ($driverCode === 1045 || str_contains($e->getMessage(), '[1045]')) {
        $transport = $port === ''
            ? ($host === 'localhost' ? 'local MySQL socket / automatic transport' : 'automatic MySQL port')
            : 'TCP port '.$port;
        return new RuntimeException(
            'MySQL rejected the database login for '.$username.'@'.$host.' using '.$transport.'. '.
            'Verify the exact database username and password assigned by your hosting panel and confirm that user is attached to database '.$database.'. '.
            'On shared hosting the full username may be prefixed, for example account_'.$username.'.',
            0,
            $e
        );
    }

    if ($driverCode === 1049 || str_contains($e->getMessage(), '[1049]')) {
        return new RuntimeException('Database '.$database.' does not exist and could not be opened.', 0, $e);
    }

    if ($driverCode === 2002 || str_contains($e->getMessage(), '[2002]')) {
        return new RuntimeException(
            'Private Gather could not reach MySQL at '.$host.($port === '' ? ' using automatic local transport.' : ' on port '.$port.'.').' '.
            'For a database on the same server, use host localhost and leave Database port blank.',
            0,
            $e
        );
    }

    return new RuntimeException('Database connection failed: '.$e->getMessage(), 0, $e);
}

/**
 * Connect to the selected database first. This supports hosting-panel-created
 * databases/users that have database privileges but do not have global CREATE
 * DATABASE permission. CREATE DATABASE is attempted only when the database is
 * actually missing and the operator requested automatic creation.
 */
function installer_connect_database_auto(array $input): PDO
{
    $host = trim((string) ($input['db_host'] ?? '')) ?: 'localhost';
    $port = installer_database_port($input);
    $database = trim((string) ($input['db_database'] ?? ''));
    $username = (string) ($input['db_username'] ?? '');
    $password = (string) ($input['db_password'] ?? '');

    if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new InvalidArgumentException('Database name may contain only letters, numbers, and underscores.');
    }

    try {
        return new PDO(
            installer_database_dsn($host, $port, $database),
            $username,
            $password,
            installer_database_pdo_options()
        );
    } catch (PDOException $e) {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $missingDatabase = $driverCode === 1049 || str_contains($e->getMessage(), '[1049]');

        if (! $missingDatabase || empty($input['db_create'])) {
            throw installer_database_exception($e, $input);
        }
    }

    try {
        $server = new PDO(
            installer_database_dsn($host, $port),
            $username,
            $password,
            installer_database_pdo_options()
        );
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        return new PDO(
            installer_database_dsn($host, $port, $database),
            $username,
            $password,
            installer_database_pdo_options()
        );
    } catch (Throwable $e) {
        throw installer_database_exception($e, $input);
    }
}

/**
 * installer_write_env() predates automatic-port mode and serializes an empty
 * port as 0. Normalize that line after the environment file is written so the
 * Laravel MySQL connector omits the port and preserves localhost socket mode.
 */
function installer_finalize_database_env(array $input): void
{
    if (trim((string) ($input['db_port'] ?? '')) !== '') {
        return;
    }

    $path = installer_base_path().'/.env';
    $contents = @file_get_contents($path);
    if (! is_string($contents) || $contents === '') {
        throw new RuntimeException('Unable to finalize automatic database transport in .env.');
    }

    $updated = preg_replace('/^DB_PORT=.*$/m', 'DB_PORT=', $contents, 1);
    if (! is_string($updated) || $updated === $contents && ! preg_match('/^DB_PORT=$/m', $contents)) {
        throw new RuntimeException('Unable to record automatic database port mode in .env.');
    }

    if (@file_put_contents($path, $updated, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save automatic database transport in .env.');
    }
    @chmod($path, 0600);
}
