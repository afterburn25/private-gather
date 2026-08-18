<?php

namespace App\Console\Commands;

use App\Services\Upgrade\UpgradeService;
use Illuminate\Console\Command;

class InstallUpgrade extends Command
{
    protected $signature = 'platform:upgrade {zip : Absolute or project-relative upgrade ZIP path}';
    protected $description = 'Install a validated platform upgrade ZIP using the same engine as the browser Update Center';

    public function handle(UpgradeService $service): int
    {
        $path = (string) $this->argument('zip');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error('Upgrade ZIP not found: '.$path);
            return self::FAILURE;
        }

        try {
            $upgrade = $service->install($path, null);
            $this->info("Upgrade completed: {$upgrade->from_version} -> {$upgrade->to_version}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Upgrade failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
