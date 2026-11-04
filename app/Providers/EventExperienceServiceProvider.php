<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Member\TicketTransferController;
use App\Http\Controllers\Tenant\EventOperationsController;
use App\Http\Controllers\Tenant\TicketManageController;
use App\Http\Middleware\EnsureTenantManager;
use App\Models\Event;
use App\Services\EventStaffAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class EventExperienceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::post('/tickets/{ticket}/transfer', [TicketTransferController::class, 'store'])
                ->whereNumber('ticket')
                ->middleware('throttle:10,1')
                ->name('member.tickets.transfer');
            Route::get('/ticket-transfer/{token}', [TicketTransferController::class, 'show'])
                ->where('token', '[A-Za-z0-9]{64}')
                ->middleware('throttle:30,1')
                ->name('member.ticket-transfer.show');
            Route::post('/ticket-transfer/{token}', [TicketTransferController::class, 'accept'])
                ->where('token', '[A-Za-z0-9]{64}')
                ->middleware('throttle:10,1')
                ->name('member.ticket-transfer.accept');
            Route::delete('/ticket-transfer/{transfer}', [TicketTransferController::class, 'revoke'])
                ->whereNumber('transfer')
                ->name('member.ticket-transfer.revoke');
        });

        Route::middleware(['web', 'auth'])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::get('/events/{event}/operations', [EventOperationsController::class, 'workspace'])
                    ->whereNumber('event')
                    ->name('events.operations');
                Route::post('/events/{event}/operations/message', [EventOperationsController::class, 'message'])
                    ->whereNumber('event')
                    ->middleware('throttle:10,1')
                    ->name('events.operations.message');
            });

        Route::middleware(['web', 'auth', EnsureTenantManager::class])
            ->prefix('manage')
            ->name('tenant.')
            ->group(function (): void {
                Route::post('/events/{event}/promos', [TicketManageController::class, 'storePromo'])
                    ->whereNumber('event')
                    ->name('events.promos.store');
                Route::delete('/events/{event}/promos/{promo}', [TicketManageController::class, 'deactivatePromo'])
                    ->whereNumber('event')
                    ->whereNumber('promo')
                    ->name('events.promos.deactivate');
                Route::patch('/events/{event}/guest-policy', [EventOperationsController::class, 'updateGuestPolicy'])
                    ->whereNumber('event')
                    ->name('events.guest-policy.update');
                Route::post('/events/{event}/staff', [EventOperationsController::class, 'assignStaff'])
                    ->whereNumber('event')
                    ->name('events.staff.store');
                Route::delete('/events/{event}/staff/{assignment}', [EventOperationsController::class, 'deactivateStaff'])
                    ->whereNumber('event')
                    ->whereNumber('assignment')
                    ->name('events.staff.deactivate');
            });

        View::composer('tenant.manage.events.edit', function ($view): void {
            $event = $view->getData()['event'] ?? null;
            $promos = collect();
            $staffAssignments = collect();
            $staffCandidates = collect();
            if ($event instanceof Event && $event->exists) {
                $promos = DB::table('promo_codes')
                    ->where('tenant_id', $event->tenant_id)
                    ->where('event_id', $event->id)
                    ->orderByDesc('active')
                    ->orderByDesc('id')
                    ->get();
                $staffAssignments = DB::table('event_staff_assignments as assignments')
                    ->join('users', 'users.id', '=', 'assignments.user_id')
                    ->where('assignments.tenant_id', $event->tenant_id)
                    ->where('assignments.event_id', $event->id)
                    ->orderByDesc('assignments.status')
                    ->orderBy('users.display_name')
                    ->get(['assignments.*', 'users.name', 'users.display_name', 'users.email']);
                $staffCandidates = app(EventStaffAccessService::class)->assignableUsers($event);
            }
            $view->with([
                'eventPromos' => $promos,
                'eventStaffAssignments' => $staffAssignments,
                'eventStaffCandidates' => $staffCandidates,
            ]);
        });
    }
}
