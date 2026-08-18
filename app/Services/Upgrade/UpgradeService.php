<?php

namespace App\Services\Upgrade;

use App\Models\PlatformUpgrade;
use App\Support\UpgradePath;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class UpgradeService
{
    public function __construct(private readonly DatabaseBackupService $databaseBackup)
    {
    }

    public function install(string $zipPath, ?int $userId = null): PlatformUpgrade
    {
        @set_time_limit(0);

        $lockPath = rtrim((string) config('upgrades.storage_path'), '/').'/upgrade.lock';
        File::ensureDirectoryExists(dirname($lockPath));
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another platform upgrade is already running.');
        }

        try {
            return $this->runLocked($zipPath, $userId);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runLocked(string $zipPath, ?int $userId): PlatformUpgrade
    {
        $package = UpgradePackage::open($zipPath);
        $package->verifySignatureIfRequired();

        $currentVersion = trim((string) config('release.version', '0.0.0'));
        if (! in_array($currentVersion, $package->fromVersions(), true)) {
            throw new RuntimeException("This package cannot upgrade version {$currentVersion}. Supported source versions: ".implode(', ', $package->fromVersions()).'.');
        }
        if (version_compare($package->toVersion(), $currentVersion, '<=')) {
            throw new RuntimeException("Upgrade target {$package->toVersion()} must be newer than installed version {$currentVersion}.");
        }

        $upgradeId = (string) Str::uuid();
        $root = rtrim((string) config('upgrades.storage_path'), '/');
        $stage = "{$root}/staging/{$upgradeId}";
        $backup = "{$root}/backups/{$upgradeId}";
        $logPath = "{$root}/logs/{$upgradeId}.json";
        $logger = new UpgradeLogger();
        $packageHash = hash_file('sha256', $zipPath);

        $record = PlatformUpgrade::create([
            'upgrade_id' => $upgradeId,
            'from_version' => $currentVersion,
            'to_version' => $package->toVersion(),
            'status' => 'validating',
            'package_name' => basename($zipPath),
            'package_sha256' => $packageHash,
            'backup_path' => $backup,
            'log_path' => $logPath,
            'initiated_by' => $userId,
            'started_at' => now(),
            'manifest' => $package->manifest,
        ]);

        $maintenance = false;
        $migrationsAttempted = false;
        $migrationsCompleted = false;

        try {
            $logger->add('validate', 'pass', 'Manifest and package identity accepted.', [
                'from' => $currentVersion,
                'to' => $package->toVersion(),
                'sha256' => $packageHash,
            ]);

            $record->update(['status' => 'staging']);
            $package->extractTo($stage);
            $this->assertVersionPayload($stage, $package->toVersion());
            $this->preflightFilesystem($package, $stage);
            $this->assertDiskSpace($package, $stage);
            $logger->add('stage', 'pass', 'All payload files staged and verified.');

            $record->update(['status' => 'backing_up_files']);
            File::ensureDirectoryExists($backup.'/files');
            $metadata = $this->backupFiles($package, $backup.'/files');
            file_put_contents($backup.'/files.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
            $logger->add('file_backup', 'pass', 'Files affected by the release were backed up.', ['backup' => $backup]);

            $record->update(['status' => 'maintenance']);
            $downExit = Artisan::call('down');
            if ($downExit !== 0) {
                throw new RuntimeException('Unable to place the platform into maintenance mode. '.trim(Artisan::output()));
            }
            $maintenance = true;
            $logger->add('maintenance', 'pass', 'Platform entered maintenance mode.');

            $record->update(['status' => 'backing_up_database']);
            $this->databaseBackup->create($backup.'/database.sql');
            $logger->add('database_backup', 'pass', 'A consistent database dump was created while the platform was in maintenance mode.');

            // Activate all file changes before migrations. File activation is
            // fully reversible from the file backup; database DDL may not be.
            // This ordering removes the dangerous state where migrations finish
            // and a later file-copy failure leaves the schema ahead of the code.
            $record->update(['status' => 'installing_files']);
            $this->applyFiles($package, $stage, $upgradeId);
            $logger->add('files', 'pass', 'Application file changes were installed.');

            if ($package->migrations() !== []) {
                $record->update(['status' => 'migrating']);
                $migrationsAttempted = true;
                $migrationStage = $this->prepareMigrations($package, $stage);
                $exit = Artisan::call('migrate', [
                    '--path' => $migrationStage,
                    '--realpath' => true,
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
                if ($exit !== 0) {
                    throw new RuntimeException('Database migration failed. '.trim(Artisan::output()));
                }
                $migrationsCompleted = true;
                $logger->add('migrations', 'pass', 'Database migrations completed.', ['output' => trim(Artisan::output())]);
            } else {
                $logger->add('migrations', 'pass', 'No database migrations were required.');
            }

            $record->update(['status' => 'finalizing']);
            Artisan::call('optimize:clear');
            $logger->add('cache', 'pass', 'Laravel caches were cleared.', ['output' => trim(Artisan::output())]);

            $upExit = Artisan::call('up');
            if ($upExit !== 0) {
                throw new RuntimeException('Upgrade installed, but maintenance mode could not be disabled automatically.');
            }
            $maintenance = false;

            $record->update([
                'status' => 'completed',
                'completed_at' => now(),
                'error_message' => null,
            ]);
            $logger->add('complete', 'pass', "Upgrade {$currentVersion} → {$package->toVersion()} completed.");
            $logger->write($logPath);

            $this->cleanupStage($stage);
            @unlink($zipPath);
            $this->pruneBackups();

            return $record->fresh();
        } catch (Throwable $e) {
            $logger->add('failure', 'fail', $e->getMessage(), ['migrations_attempted' => $migrationsAttempted, 'migrations_completed' => $migrationsCompleted]);

            try {
                if (is_file($backup.'/files.json')) {
                    $this->restoreFiles($backup);
                    $logger->add('rollback', 'pass', 'Application files were restored from the pre-upgrade backup.');
                }
            } catch (Throwable $rollbackError) {
                $logger->add('rollback', 'fail', $rollbackError->getMessage());
            }

            try {
                Artisan::call('optimize:clear');
            } catch (Throwable) {
                // Best effort only while handling another failure.
            }

            if ($maintenance) {
                try {
                    $upExit = Artisan::call('up');
                    if ($upExit === 0) {
                        $maintenance = false;
                    } else {
                        $logger->add('maintenance', 'fail', 'Could not leave maintenance mode: '.trim(Artisan::output()));
                    }
                } catch (Throwable $upError) {
                    $logger->add('maintenance', 'fail', 'Could not leave maintenance mode: '.$upError->getMessage());
                }
            }

            $record->update([
                'status' => $migrationsCompleted ? 'failed_files_restored_schema_advanced' : ($migrationsAttempted ? 'failed_database_may_be_modified' : 'failed'),
                'completed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 65000),
            ]);
            $logger->write($logPath);
            $this->cleanupStage($stage);
            @unlink($zipPath);

            throw $e;
        }
    }

    private function assertVersionPayload(string $stage, string $toVersion): void
    {
        $versionFile = $stage.'/VERSION';
        if (! is_file($versionFile)) {
            throw new RuntimeException('Every upgrade must replace the root VERSION file.');
        }
        if (trim((string) file_get_contents($versionFile)) !== $toVersion) {
            throw new RuntimeException('The VERSION payload does not match manifest to_version.');
        }
    }

    private function preflightFilesystem(UpgradePackage $package, string $stage): void
    {
        foreach ($package->files() as $file) {
            $path = UpgradePath::normalize((string) $file['path']);
            $action = (string) ($file['action'] ?? 'replace');
            $target = base_path($path);

            if (is_link($target)) {
                throw new RuntimeException("Upgrade target may not be a symbolic link: {$path}");
            }

            if (is_dir($target)) {
                throw new RuntimeException("Upgrade target is a directory, not a file: {$path}");
            }

            if ($action === 'replace' && ! is_file($stage.'/'.$path)) {
                throw new RuntimeException("Staged file is missing: {$path}");
            }

            $parent = dirname($target);
            while (! is_dir($parent) && $parent !== dirname($parent)) {
                $parent = dirname($parent);
            }
            $realBase = realpath(base_path());
            $realParent = realpath($parent);
            if ($realBase === false || $realParent === false || ($realParent !== $realBase && ! str_starts_with($realParent, $realBase.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException("Upgrade path escapes the application root: {$path}");
            }
            if (! is_writable($parent)) {
                throw new RuntimeException("Upgrade target is not writable: {$path}");
            }
            if (is_file($target) && ! is_writable($target)) {
                throw new RuntimeException("Existing file is not writable: {$path}");
            }
        }
    }

    private function assertDiskSpace(UpgradePackage $package, string $stage): void
    {
        $required = 10 * 1024 * 1024;
        foreach ($package->files() as $file) {
            if (($file['action'] ?? 'replace') !== 'replace') {
                continue;
            }
            $path = UpgradePath::normalize((string) $file['path']);
            $required += (int) (@filesize($stage.'/'.$path) ?: 0);
            $required += (int) (@filesize(base_path($path)) ?: 0);
        }

        // Account for a database backup with a conservative floor based on the
        // current database file workload that cannot be known exactly in MySQL.
        $required *= 3;
        $free = @disk_free_space(storage_path('app/upgrades'));
        if ($free !== false && $free < $required) {
            throw new RuntimeException('Not enough free disk space to stage, back up, and install this upgrade safely.');
        }
    }

    private function backupFiles(UpgradePackage $package, string $backupFiles): array
    {
        $metadata = [];
        foreach ($package->files() as $file) {
            $path = UpgradePath::normalize((string) $file['path']);
            $source = base_path($path);
            $exists = is_file($source);
            $metadata[] = [
                'path' => $path,
                'existed' => $exists,
                'action' => (string) ($file['action'] ?? 'replace'),
            ];

            if ($exists) {
                $destination = $backupFiles.'/'.$path;
                File::ensureDirectoryExists(dirname($destination));
                if (! copy($source, $destination)) {
                    throw new RuntimeException("Unable to back up {$path}.");
                }
            }
        }

        return $metadata;
    }

    private function prepareMigrations(UpgradePackage $package, string $stage): string
    {
        $path = $stage.'/.upgrade-migrations';
        File::ensureDirectoryExists($path);
        $seen = [];

        foreach ($package->migrations() as $migration) {
            $migration = UpgradePath::normalize((string) $migration);
            $name = basename($migration);
            if (isset($seen[$name])) {
                throw new RuntimeException("Duplicate migration filename in package: {$name}");
            }
            $seen[$name] = true;
            if (! copy($stage.'/'.$migration, $path.'/'.$name)) {
                throw new RuntimeException("Unable to prepare migration {$name}.");
            }
        }

        return $path;
    }

    private function applyFiles(UpgradePackage $package, string $stage, string $upgradeId): void
    {
        foreach ($package->files() as $file) {
            $path = UpgradePath::normalize((string) $file['path']);
            $action = (string) ($file['action'] ?? 'replace');
            $target = base_path($path);

            if ($action === 'delete') {
                if (is_file($target) && ! @unlink($target)) {
                    throw new RuntimeException("Unable to delete obsolete file {$path}.");
                }
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            $temp = $target.'.upgrade-'.$upgradeId.'.tmp';
            if (! copy($stage.'/'.$path, $temp)) {
                throw new RuntimeException("Unable to write replacement for {$path}.");
            }
            @chmod($temp, 0644);

            if (! @rename($temp, $target)) {
                @unlink($temp);
                throw new RuntimeException("Unable to atomically activate {$path}.");
            }
        }
    }

    private function restoreFiles(string $backup): void
    {
        $metadata = json_decode((string) file_get_contents($backup.'/files.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (array_reverse($metadata) as $file) {
            $path = UpgradePath::normalize((string) $file['path']);
            $target = base_path($path);

            if ((bool) $file['existed']) {
                $source = $backup.'/files/'.$path;
                File::ensureDirectoryExists(dirname($target));
                $temp = $target.'.rollback-'.bin2hex(random_bytes(6)).'.tmp';
                if (! copy($source, $temp)) {
                    throw new RuntimeException("Unable to stage rollback for {$path}.");
                }
                @chmod($temp, 0644);
                if (! @rename($temp, $target)) {
                    @unlink($temp);
                    throw new RuntimeException("Unable to atomically restore {$path}.");
                }
            } elseif (is_file($target) && ! @unlink($target)) {
                throw new RuntimeException("Unable to remove newly introduced file {$path} during rollback.");
            }
        }
    }

    private function cleanupStage(string $stage): void
    {
        if (is_dir($stage)) {
            File::deleteDirectory($stage);
        }
    }

    private function pruneBackups(): void
    {
        $keep = max(1, (int) config('upgrades.keep_backups', 5));
        $root = rtrim((string) config('upgrades.storage_path'), '/').'/backups';
        if (! is_dir($root)) {
            return;
        }

        $dirs = array_values(array_filter(glob($root.'/*') ?: [], 'is_dir'));
        usort($dirs, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        foreach (array_slice($dirs, $keep) as $dir) {
            File::deleteDirectory($dir);
        }
    }
}
