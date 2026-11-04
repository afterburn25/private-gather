<?php

declare(strict_types=1);

/**
 * Return an explicit port only when the operator supplied one.
 * Blank means Auto; localhost Auto probes native socket first and forced TCP
 * second rather than assuming both choices are the same transport.
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

/**
 * Build genuinely different transport candidates.
 *
 * Important PDO/MySQL behavior: host=localhost can use the Unix socket even
 * when a port is present. Therefore an explicitly entered port with localhost
 * is converted to 127.0.0.1 so that the operator actually gets TCP.
 *
 * Auto on localhost tries the native socket first, then forced TCP 3306. The
 * successful transport is persisted into the install input so the generated
 * Laravel environment uses the same route that was proven to work.
 */
function installer_database_candidates(array $input, ?string $database = null): array
{
    $host = trim((string) ($input['db_host'] ?? '')) ?: 'localhost';
    $port = installer_database_port($input);
    $candidates = [];

    if (strcasecmp($host, 'localhost') === 0) {
        if ($port !== null) {
            $candidates[] = [
                'dsn' => installer_database_dsn('127.0.0.1', $port, $database),
                'server_dsn' => installer_database_dsn('127.0.0.1', $port),
                'label' => 'forced TCP 127.0.0.1:'.$port,
                'env_host' => '127.0.0.1',
                'env_port' => (string) $port,
            ];

            return $candidates;
        }

        $candidates[] = [
            'dsn' => installer_database_dsn('localhost', null, $database),
            'server_dsn' => installer_database_dsn('localhost', null),
            'label' => 'local MySQL socket / native localhost transport',
            'env_host' => 'localhost',
            'env_port' => '',
        ];
        $candidates[] = [
            'dsn' => installer_database_dsn('127.0.0.1', 3306, $database),
            'server_dsn' => installer_database_dsn('127.0.0.1', 3306),
            'label' => 'forced TCP 127.0.0.1:3306 fallback',
            'env_host' => '127.0.0.1',
            'env_port' => '3306',
        ];

        return $candidates;
    }

    $candidates[] = [
        'dsn' => installer_database_dsn($host, $port, $database),
        'server_dsn' => installer_database_dsn($host, $port),
        'label' => $port === null ? $host.' using automatic MySQL port' : $host.':'.$port.' over TCP',
        'env_host' => $host,
        'env_port' => $port === null ? '' : (string) $port,
    ];

    return $candidates;
}

function installer_database_driver_code(Throwable $e): int
{
    return $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;
}

function installer_database_is_code(Throwable $e, int $code): bool
{
    return installer_database_driver_code($e) === $code || str_contains($e->getMessage(), '['.$code.']');
}

function installer_apply_database_transport(array &$input, array $candidate): void
{
    $input['db_host'] = (string) $candidate['env_host'];
    $input['db_port'] = (string) $candidate['env_port'];
    $input['db_transport'] = (string) $candidate['label'];
}

function installer_database_exception(Throwable $e, array $input, array $attempts = []): RuntimeException
{
    $driverCode = installer_database_driver_code($e);
    $host = trim((string) ($input['db_host'] ?? 'localhost')) ?: 'localhost';
    $port = trim((string) ($input['db_port'] ?? ''));
    $username = trim((string) ($input['db_username'] ?? ''));
    $database = trim((string) ($input['db_database'] ?? ''));
    $labels = array_values(array_unique(array_filter(array_map(
        static fn (array $attempt): string => (string) ($attempt['label'] ?? ''),
        $attempts
    ))));
    $attemptText = $labels === [] ? '' : ' Tried: '.implode('; ', $labels).'.';

    if ($driverCode === 1045 || str_contains($e->getMessage(), '[1045]')) {
        $allAuthRejected = $attempts !== [] && count(array_filter(
            $attempts,
            static fn (array $attempt): bool => (int) ($attempt['code'] ?? 0) === 1045
        )) === count($attempts);

        if ($allAuthRejected && count($labels) > 1) {
            return new RuntimeException(
                'MySQL rejected the database login on every transport Private Gather tested for '.$username.'.'.$attemptText.' '.
                'Because both the local socket and forced TCP path rejected the same credentials, this is no longer just a port-selection problem. '.
                'Verify the exact database username and password and confirm that user is assigned to database '.$database.' with privileges. '.
                'On hosting panels the actual database username may include an account prefix.',
                0,
                $e
            );
        }

        return new RuntimeException(
            'MySQL rejected the database login for '.$username.'@'.$host.'.'.$attemptText.' '.
            'Verify the exact database username and password assigned by your hosting panel and confirm that user is attached to database '.$database.'. '.
            'On hosting panels the actual database username may include an account prefix.',
            0,
            $e
        );
    }

    if ($driverCode === 1049 || str_contains($e->getMessage(), '[1049]')) {
        return new RuntimeException('Database '.$database.' does not exist and could not be opened.'.$attemptText, 0, $e);
    }

    if ($driverCode === 2002 || str_contains($e->getMessage(), '[2002]')) {
        return new RuntimeException(
            'Private Gather could not reach MySQL.'.$attemptText.' '.
            'For a database on the same server, leave Database port blank so Auto can try both local socket and forced TCP.',
            0,
            $e
        );
    }

    return new RuntimeException('Database connection failed.'.$attemptText.' '.$e->getMessage(), 0, $e);
}

/**
 * Connect to the selected database first. This supports hosting-panel-created
 * databases/users that have database privileges but do not have global CREATE
 * DATABASE permission. CREATE DATABASE is attempted only when the database is
 * actually missing and the operator requested automatic creation.
 *
 * The input is intentionally passed by reference: when Auto discovers that
 * forced TCP succeeds after localhost socket fails, the proven host/port are
 * persisted into the generated .env instead of reverting to the failed route.
 */
function installer_connect_database_auto(array &$input): PDO
{
    $database = trim((string) ($input['db_database'] ?? ''));
    $username = (string) ($input['db_username'] ?? '');
    $password = (string) ($input['db_password'] ?? '');

    if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        throw new InvalidArgumentException('Database name may contain only letters, numbers, and underscores.');
    }

    $attempts = [];
    $lastError = null;

    foreach (installer_database_candidates($input, $database) as $candidate) {
        try {
            $pdo = new PDO(
                (string) $candidate['dsn'],
                $username,
                $password,
                installer_database_pdo_options()
            );
            installer_apply_database_transport($input, $candidate);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
            $code = installer_database_driver_code($e);
            $attempts[] = ['label' => $candidate['label'], 'code' => $code];

            $missingDatabase = installer_database_is_code($e, 1049);
            if ($missingDatabase && ! empty($input['db_create'])) {
                try {
                    $server = new PDO(
                        (string) $candidate['server_dsn'],
                        $username,
                        $password,
                        installer_database_pdo_options()
                    );
                    $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO(
                        (string) $candidate['dsn'],
                        $username,
                        $password,
                        installer_database_pdo_options()
                    );
                    installer_apply_database_transport($input, $candidate);
                    return $pdo;
                } catch (Throwable $createError) {
                    $lastError = $createError;
                    $attempts[] = [
                        'label' => (string) $candidate['label'].' database-create attempt',
                        'code' => installer_database_driver_code($createError),
                    ];
                }
            }

            // In localhost Auto mode, authentication and reachability failures
            // on the socket are allowed to fall through to the forced TCP
            // candidate. This is the important distinction that plain
            // localhost:3306 does not reliably provide with PDO/MySQL.
            continue;
        }
    }

    $lastError ??= new RuntimeException('No database connection strategy was available.');
    throw installer_database_exception($lastError, $input, $attempts);
}

/**
 * installer_write_env() predates automatic-port mode and serializes an empty
 * port as 0. Normalize that line after the environment file is written so the
 * Laravel MySQL connector omits the port and preserves the transport selected
 * by installer_connect_database_auto().
 */
function installer_finalize_database_env(array $input): void
{
    $path = installer_base_path().'/.env';
    $contents = @file_get_contents($path);
    if (! is_string($contents) || $contents === '') {
        throw new RuntimeException('Unable to finalize database transport in .env.');
    }

    $host = trim((string) ($input['db_host'] ?? '')) ?: 'localhost';
    $port = trim((string) ($input['db_port'] ?? ''));
    $updated = preg_replace('/^DB_HOST=.*$/m', 'DB_HOST='.installer_quote_env($host), $contents, 1);
    if (! is_string($updated)) {
        throw new RuntimeException('Unable to record the proven database host in .env.');
    }
    $updated = preg_replace('/^DB_PORT=.*$/m', 'DB_PORT='.$port, $updated, 1);
    if (! is_string($updated)) {
        throw new RuntimeException('Unable to record the proven database port in .env.');
    }

    if (@file_put_contents($path, $updated, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save proven database transport in .env.');
    }
    @chmod($path, 0600);
}
