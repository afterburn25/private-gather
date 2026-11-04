<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SystemHealth
{
    public function report(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $value, bool $required = true) use (&$checks): void {
            $checks[$name] = ['ok' => $ok, 'value' => $value, 'required' => $required];
        };

        $add('php', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION.' · required >= 8.3');
        foreach (['pdo','openssl','mbstring','fileinfo','zip'] as $ext) {
            $add('ext_'.$ext, extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing');
        }

        try {
            DB::select('SELECT 1');
            $add('database', true, DB::connection()->getDatabaseName());
            $pending = method_exists(DB::connection(), 'getDriverName') ? DB::table('migrations')->count() : 0;
            $add('migration_registry', $pending > 0, $pending.' applied migration record(s)');
        } catch (Throwable $e) {
            $add('database', false, 'connection failed');
            $add('migration_registry', false, 'unavailable');
        }

        try {
            $probe = '.health/'.bin2hex(random_bytes(6));
            $ok = Storage::disk('local')->put($probe, 'ok');
            if ($ok) Storage::disk('local')->delete($probe);
            $add('private_storage', (bool)$ok, $ok ? 'writable' : 'write failed');
        } catch (Throwable) {
            $add('private_storage', false, 'write failed');
        }

        $keyOk = strlen((string) config('app.key')) > 20;
        $add('app_key', $keyOk, $keyOk ? 'configured' : 'missing/weak');
        $production = app()->environment('production');
        $add('production_debug', ! $production || ! config('app.debug'), config('app.debug') ? 'debug on' : 'debug off');
        $add('session_encryption', (bool) config('session.encrypt'), config('session.encrypt') ? 'enabled' : 'disabled');
        $sessionDriver = (string) config('session.driver');
        $add('session_store', ! $production || ! in_array($sessionDriver, ['array', 'cookie'], true), $sessionDriver);
        $queue = (string) config('queue.default');
        $add('queue_backend', ! $production || ! in_array($queue, ['', 'sync'], true), $queue ?: 'not configured');

        $mail = (string) config('mail.default');
        $add('mail_delivery', ! in_array($mail, ['log', 'array'], true), $mail ?: 'not configured', false);
        $disk = (string) config('filesystems.default');
        $add('object_storage', in_array($disk, ['s3', 'gcs', 'r2'], true), $disk.' (local is supported; object storage recommended for multi-node)', false);
        $add('web_push', trim((string) config('services.webpush.public_key')) !== '' && trim((string) config('services.webpush.private_key')) !== '', trim((string) config('services.webpush.public_key')) !== '' ? 'configured' : 'optional / not configured', false);
        $add('domain_registrar', trim((string) config('services.domain_registrar.endpoint')) !== '' && trim((string) config('services.domain_registrar.token')) !== '', trim((string) config('services.domain_registrar.endpoint')) !== '' ? 'configured' : 'optional / manual domain workflow', false);
        $add('observability', trim((string) config('services.observability.dsn')) !== '', trim((string) config('services.observability.dsn')) !== '' ? 'configured' : 'optional / not configured', false);

        $signatureRequired = (bool) config('upgrades.require_signature');
        $signatureKey = trim((string) config('upgrades.ed25519_public_key')) !== '';
        $add('signed_upgrades', ! $production || ($signatureRequired && $signatureKey), $signatureRequired ? ($signatureKey ? 'required + public key configured' : 'required but key missing') : 'signature enforcement off');

        $storageFree = @disk_free_space(storage_path());
        $storageTotal = @disk_total_space(storage_path());
        $freePct = ($storageFree !== false && $storageTotal) ? ($storageFree / $storageTotal) * 100 : null;
        $add('storage_capacity', $freePct === null || $freePct >= 10, $freePct === null ? 'unknown' : number_format($freePct, 1).'% free');

        $requiredHealthy = collect($checks)->filter(fn ($check) => $check['required'])->every(fn ($check) => $check['ok']);
        $optionalReady = collect($checks)->filter(fn ($check) => ! $check['required'])->filter(fn ($check) => $check['ok'])->count();
        $optionalTotal = collect($checks)->filter(fn ($check) => ! $check['required'])->count();

        return [
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'healthy' => $requiredHealthy,
            'optional_ready' => $optionalReady,
            'optional_total' => $optionalTotal,
            'checks' => $checks,
        ];
    }
}
