<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DatabaseBackupService
{
    public function create(string $targetFile): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Automatic upgrade database backups currently require MySQL/MariaDB.');
        }

        $pdo = $connection->getPdo();
        $parent = dirname($targetFile);
        if (! is_dir($parent) && ! mkdir($parent, 0775, true) && ! is_dir($parent)) {
            throw new RuntimeException('Unable to create database backup directory.');
        }

        $out = fopen($targetFile, 'wb');
        if ($out === false) {
            throw new RuntimeException('Unable to create database backup file.');
        }

        $snapshotStarted = false;

        try {
            // Maintenance mode blocks normal web writes, and the repeatable-read
            // snapshot also keeps all InnoDB table rows from drifting underneath
            // a long-running backup if a queue/cron worker is still active.
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $snapshotStarted = true;

            fwrite($out, "-- Private Gather automatic pre-upgrade backup\n");
            fwrite($out, '-- Created: '.gmdate('c')."\n");
            fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tableRows = $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            foreach ($tableRows as $row) {
                $values = array_values((array) $row);
                $table = (string) $values[0];
                $quotedTable = '`'.str_replace('`', '``', $table).'`';

                $createRows = $connection->select("SHOW CREATE TABLE {$quotedTable}");
                $create = array_values((array) $createRows[0])[1] ?? null;
                if (! is_string($create)) {
                    throw new RuntimeException("Unable to read schema for {$table}.");
                }

                fwrite($out, "\nDROP TABLE IF EXISTS {$quotedTable};\n{$create};\n");

                if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                    try {
                        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                    } catch (Throwable) {
                    }
                }

                $statement = $pdo->query("SELECT * FROM {$quotedTable}");
                if ($statement === false) {
                    throw new RuntimeException("Unable to read rows from {$table}.");
                }

                while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $columns = array_map(
                        static fn (string $column): string => '`'.str_replace('`', '``', $column).'`',
                        array_keys($record)
                    );
                    $valuesSql = [];
                    foreach ($record as $value) {
                        $valuesSql[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
                    }
                    fwrite($out, "INSERT INTO {$quotedTable} (".implode(',', $columns).') VALUES ('.implode(',', $valuesSql).");\n");
                }

                $statement->closeCursor();

                if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                    try {
                        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                    } catch (Throwable) {
                    }
                }
            }

            fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");

            if ($snapshotStarted && $pdo->inTransaction()) {
                $pdo->commit();
                $snapshotStarted = false;
            }
        } catch (Throwable $e) {
            if ($snapshotStarted && $pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (Throwable) {
                }
            }
            throw $e;
        } finally {
            fclose($out);
        }
    }
}
