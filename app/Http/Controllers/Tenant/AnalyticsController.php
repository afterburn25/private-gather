<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\EventAnalyticsService;
use App\Support\CsvCell;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, TenantContext $context, EventAnalyticsService $analytics)
    {
        $tenant = $context->requireTenant();
        $days = max(1, min(365, (int) $request->integer('days', 30)));
        $from = now()->subDays($days);
        $stats = $analytics->summary($tenant->id, $from);
        $events = $analytics->events($tenant->id, $from, 30);

        return view('tenant.manage.analytics', compact('tenant', 'stats', 'events', 'from', 'days'));
    }

    public function export(Request $request, TenantContext $context, EventAnalyticsService $analytics): StreamedResponse
    {
        $tenant = $context->requireTenant();
        $days = max(1, min(365, (int) $request->integer('days', 30)));
        $from = now()->subDays($days);
        $rows = $analytics->events($tenant->id, $from, 100);
        $name = 'event-performance-'.$tenant->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Event', 'Starts', 'Status', 'Views', 'RSVPs', 'Approved party', 'Checked in', 'No shows',
                'Check-in rate %', 'RSVP conversion %', 'Paid orders', 'Discounts', 'Collected',
            ]);

            foreach ($rows as $row) {
                $event = $row['event'];
                fputcsv($out, [
                    CsvCell::safe($event->title),
                    CsvCell::safe($event->starts_at?->toIso8601String()),
                    CsvCell::safe($event->status),
                    $row['views'],
                    $row['rsvps'],
                    $row['approved_party'],
                    $row['checked_in_party'],
                    $row['no_show_party'],
                    $row['checkin_rate'],
                    $row['rsvp_conversion'],
                    $row['paid_orders'],
                    number_format($row['discount_cents'] / 100, 2, '.', ''),
                    number_format($row['collected_cents'] / 100, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
