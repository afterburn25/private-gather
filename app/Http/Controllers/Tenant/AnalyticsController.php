<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventRsvp;
use App\Models\Order;
use App\Support\CsvCell;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        $tenant = $context->requireTenant();
        $from = now()->subDays(max(1, min(365, (int) $request->integer('days', 30))));

        $availability = [
            'analytics_events' => Schema::hasTable('analytics_events'),
            'event_rsvps' => Schema::hasTable('event_rsvps'),
            'event_checkins' => Schema::hasTable('event_checkins'),
            'orders' => Schema::hasTable('orders'),
            'events' => Schema::hasTable('events'),
            'tenant_users' => Schema::hasTable('tenant_users'),
            'community_posts' => Schema::hasTable('community_posts'),
            'community_comments' => Schema::hasTable('community_comments'),
            'conversations' => Schema::hasTable('conversations'),
            'messages' => Schema::hasTable('messages'),
            'community_groups' => Schema::hasTable('community_groups'),
            'private_albums' => Schema::hasTable('private_albums'),
            'private_album_photos' => Schema::hasTable('private_album_photos'),
        ];

        $stats = [
            'event_views' => $availability['analytics_events']
                ? AnalyticsEvent::where('tenant_id', $tenant->id)->where('event_name', 'event.view')->where('occurred_at', '>=', $from)->count()
                : 0,
            'rsvps' => $availability['event_rsvps'] && $availability['events']
                ? EventRsvp::whereHas('event', fn ($query) => $query->where('tenant_id', $tenant->id))->where('created_at', '>=', $from)->count()
                : 0,
            'checkins' => $availability['event_checkins'] && $availability['events']
                ? EventCheckin::whereHas('event', fn ($query) => $query->where('tenant_id', $tenant->id))->where('checked_in_at', '>=', $from)->count()
                : 0,
            'orders' => $availability['orders']
                ? Order::where('tenant_id', $tenant->id)->where('created_at', '>=', $from)->count()
                : 0,
            'gross_cents' => $availability['orders']
                ? (int) Order::where('tenant_id', $tenant->id)->whereIn('status', ['paid', 'completed'])->where('created_at', '>=', $from)->sum('total_cents')
                : 0,
            'active_members' => $availability['tenant_users'] ? DB::table('tenant_users')->where('tenant_id',$tenant->id)->where('status','active')->count() : 0,
            'community_posts' => $availability['community_posts'] ? DB::table('community_posts')->where('tenant_id',$tenant->id)->where('status','active')->where('created_at','>=',$from)->count() : 0,
            'comments' => $availability['community_comments'] ? DB::table('community_comments')->where('tenant_id',$tenant->id)->where('status','active')->where('created_at','>=',$from)->count() : 0,
            'messages' => $availability['messages'] && $availability['conversations'] ? DB::table('messages')->join('conversations','conversations.id','=','messages.conversation_id')->where('conversations.tenant_id',$tenant->id)->where('messages.created_at','>=',$from)->whereNull('messages.deleted_at')->count() : 0,
            'groups' => $availability['community_groups'] ? DB::table('community_groups')->where('tenant_id',$tenant->id)->where('status','active')->count() : 0,
            'gallery_media' => $availability['private_albums'] && $availability['private_album_photos'] ? DB::table('private_album_photos')->join('private_albums','private_albums.id','=','private_album_photos.album_id')->where('private_albums.tenant_id',$tenant->id)->where('private_albums.status','active')->count() : 0,
        ];

        if ($availability['events']) {
            $eventsQuery = Event::where('tenant_id', $tenant->id)->latest('starts_at')->limit(20);
            if ($availability['event_rsvps']) {
                $eventsQuery->withCount('rsvps');
            }
            $events = $eventsQuery->get();
            if (! $availability['event_rsvps']) {
                $events->each(fn (Event $event) => $event->setAttribute('rsvps_count', 0));
            }
        } else {
            $events = collect();
        }

        return view('tenant.manage.analytics', compact('tenant', 'stats', 'events', 'from', 'availability'));
    }

    public function export(TenantContext $context): StreamedResponse
    {
        $tenant = $context->requireTenant();
        $name = 'events-'.$tenant->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($tenant): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'Starts', 'Status', 'Capacity', 'RSVPs']);

            if (! Schema::hasTable('events')) {
                fclose($out);
                return;
            }

            $hasRsvps = Schema::hasTable('event_rsvps');
            $query = Event::where('tenant_id', $tenant->id)->orderBy('starts_at');
            if ($hasRsvps) {
                $query->withCount('rsvps');
            }

            $query->chunk(200, function ($events) use ($out, $hasRsvps): void {
                foreach ($events as $event) {
                    fputcsv($out, [
                        CsvCell::safe($event->title),
                        CsvCell::safe($event->starts_at?->toIso8601String()),
                        CsvCell::safe($event->status),
                        $event->capacity,
                        $hasRsvps ? (int) $event->rsvps_count : 0,
                    ]);
                }
            });

            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
