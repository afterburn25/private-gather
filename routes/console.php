<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('platform:about', function () {
    $this->info('Private Gather foundation');
    $this->line('Root domain: '.config('platform.root_domain'));
    $this->line('Custom-domain target: '.config('platform.domain_target'));
})->purpose('Show platform foundation information');
