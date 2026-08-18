<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use Throwable;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
        {name : Club, organizer, or private-host display name}
        {subdomain : Requested platform subdomain}
        {--type=club : club|organizer|private_host}';

    protected $description = 'Provision a tenant with an immediate platform subdomain and starter CMS homepage.';

    public function handle(TenantProvisioner $provisioner): int
    {
        $type = (string) $this->option('type');
        $allowed = [Tenant::TYPE_CLUB, Tenant::TYPE_ORGANIZER, Tenant::TYPE_PRIVATE_HOST];

        if (! in_array($type, $allowed, true)) {
            $this->error('Invalid type. Use: '.implode(', ', $allowed));
            return self::FAILURE;
        }

        try {
            $tenant = $provisioner->create((string) $this->argument('name'), $type, (string) $this->argument('subdomain'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Tenant created: '.$tenant->name);
        $this->line('Site: https://'.$tenant->primaryDomain?->domain);
        return self::SUCCESS;
    }
}
