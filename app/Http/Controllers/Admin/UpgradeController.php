<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformUpgrade;
use App\Services\Upgrade\UpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class UpgradeController extends Controller
{
    public function index(): View
    {
        $history = PlatformUpgrade::query()->latest('started_at')->limit(25)->get();

        return view('admin.upgrades.index', [
            'history' => $history,
            'currentVersion' => config('release.version'),
            'maxUploadMb' => config('upgrades.max_upload_mb'),
            'signatureRequired' => config('upgrades.require_signature'),
        ]);
    }

    public function store(Request $request, UpgradeService $service): RedirectResponse
    {
        $maxKb = max(1024, (int) config('upgrades.max_upload_mb', 256) * 1024);
        $validated = $request->validate([
            // The client filename/extension is not a security boundary. The
            // package service opens the bytes with ZipArchive and validates the
            // complete archive envelope/manifest before any code is activated.
            'upgrade' => ['required', 'file', 'max:'.$maxKb],
            'confirm_backup' => ['accepted'],
        ]);

        $file = $validated['upgrade'];
        $incoming = rtrim((string) config('upgrades.storage_path'), '/').'/incoming';
        File::ensureDirectoryExists($incoming);
        $safeName = now()->format('Ymd-His').'-'.Str::lower(Str::random(10)).'.zip';
        $file->move($incoming, $safeName);
        $path = $incoming.'/'.$safeName;

        try {
            $upgrade = $service->install($path, $request->user()?->id);
            return redirect()->route('admin.upgrades.index')->with('success', "Upgrade to {$upgrade->to_version} completed successfully.");
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('admin.upgrades.index')->withErrors([
                'upgrade' => 'Upgrade failed: '.$e->getMessage(),
            ]);
        } finally {
            // UpgradeService also deletes accepted packages after processing,
            // but invalid ZIPs/signatures can fail before a PlatformUpgrade
            // record exists. Always remove the random incoming file here so
            // repeated invalid uploads cannot fill server storage.
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function log(PlatformUpgrade $upgrade): BinaryFileResponse
    {
        abort_unless($upgrade->log_path && is_file($upgrade->log_path), 404);
        return response()->download($upgrade->log_path, 'upgrade-'.$upgrade->upgrade_id.'-log.json', [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function backup(PlatformUpgrade $upgrade): BinaryFileResponse
    {
        abort_unless($upgrade->backup_path && is_dir($upgrade->backup_path), 404);
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to download an upgrade backup bundle.');
        }

        $zipPath = $upgrade->backup_path.'/backup-bundle.zip';
        if (! is_file($zipPath)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create backup bundle.');
            }
            try {
                $rootLength = strlen(rtrim($upgrade->backup_path, DIRECTORY_SEPARATOR)) + 1;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($upgrade->backup_path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iterator as $item) {
                    if (! $item->isFile() || $item->getPathname() === $zipPath) {
                        continue;
                    }
                    $relative = str_replace('\\', '/', substr($item->getPathname(), $rootLength));
                    $zip->addFile($item->getPathname(), $relative);
                }
            } finally {
                $zip->close();
            }
        }

        return response()->download($zipPath, 'upgrade-'.$upgrade->upgrade_id.'-backup.zip', [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
