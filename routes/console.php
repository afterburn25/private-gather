<?php

use App\Services\CommunicationsService;
use App\Services\MembershipLifecycleService;
use App\Services\TenantAnnouncementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('platform:about', function () {
    $this->info('Private Gather foundation');
    $this->line('Root domain: '.config('platform.root_domain'));
    $this->line('Custom-domain target: '.config('platform.domain_target'));
})->purpose('Show platform foundation information');

Artisan::command('private-gather:publish-scheduled-announcements {--limit=100}', function () {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $publisher = app(TenantAnnouncementService::class);
    $due = DB::table('tenant_announcements')
        ->where('status', 'scheduled')
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now())
        ->orderBy('published_at')
        ->orderBy('id')
        ->limit($limit)
        ->get(['id', 'tenant_id']);

    $published = 0;
    $deferred = 0;
    foreach ($due as $announcement) {
        try {
            if ($publisher->publish((int) $announcement->tenant_id, (int) $announcement->id, null, null, 'Private Gather Scheduler', true, 'scheduler')) {
                $published++;
            }
        } catch (\Throwable $exception) {
            $deferred++;
            $this->warn('Announcement '.$announcement->id.' was left scheduled: '.$exception->getMessage());
        }
    }

    $this->info('Published '.$published.' scheduled announcement(s).');
    if ($deferred > 0) $this->line($deferred.' due announcement(s) remain scheduled because their current audience could not be resolved safely.');
})->purpose('Publish tenant announcements whose scheduled time has arrived');

Artisan::command('private-gather:process-membership-lifecycle {--limit=250}', function () {
    $counts = app(MembershipLifecycleService::class)->process((int) $this->option('limit'));
    $this->info('Membership lifecycle processed.');
    foreach ($counts as $label => $count) {
        $this->line(str_replace('_', ' ', ucfirst($label)).': '.$count);
    }
})->purpose('Send renewal reminders and reconcile membership renewal, grace, expiry and invitation state');

Artisan::command('private-gather:process-communications {--limit=100}', function () {
    $counts = app(CommunicationsService::class)->process((int) $this->option('limit'));
    $this->info('Communications queue processed.');
    foreach ($counts as $label => $count) {
        $this->line(str_replace('_', ' ', ucfirst($label)).': '.$count);
    }
})->purpose('Resolve consent-aware campaign recipients and process queued communications');

Schedule::command('private-gather:publish-scheduled-announcements --limit=100')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('private-gather:process-communications --limit=100')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('private-gather:process-membership-lifecycle --limit=250')
    ->hourly()
    ->withoutOverlapping();
