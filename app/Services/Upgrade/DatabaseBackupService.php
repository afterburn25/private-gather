<?php

namespace App\Services\Upgrade;

use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        try {
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
                    try { $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); } catch (\Throwable) {}
                }
                $statement = $pdo->query("SELECT * FROM {$quotedTable}");
                while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $columns = array_map(static fn (string $column): string => '`'.str_replace('`', '``', $column).'`', array_keys($record));
                    $valuesSql = [];
                    foreach ($record as $value) {
                        $valuesSql[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
                    }
                    fwrite($out, "INSERT INTO {$quotedTable} (".implode(',', $columns).') VALUES ('.implode(',', $valuesSql).");\n");
                }
                $statement->closeCursor();
                if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                    try { $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); } catch (\Throwable) {}
                }
            }

            fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($out);
        }
    }
}
