<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Member\CommunitySearchController;
use App\Http\Controllers\Member\EventDiscussionController;
use App\Http\Middleware\EnsureTenantMember;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\NetworkCommunityComment;
use App\Models\NetworkCommunityPost;
use App\Services\CommunityMentionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class CommunityEnhancementsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantMember::class])->group(function (): void {
            Route::get('/community/search', [CommunitySearchController::class, 'tenant'])->name('community.search');
            Route::get('/community/events/{event}', [EventDiscussionController::class, 'tenant'])->whereNumber('event')->name('community.events.show');
            Route::post('/community/events/{event}', [EventDiscussionController::class, 'tenantStore'])->whereNumber('event')->name('community.events.posts.store');
        });
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/community/network/search', [CommunitySearchController::class, 'network'])->name('network-community.search');
            Route::get('/community/network/events/{event}', [EventDiscussionController::class, 'network'])->whereNumber('event')->name('network-community.events.show');
            Route::post('/community/network/events/{event}', [EventDiscussionController::class, 'networkStore'])->whereNumber('event')->name('network-community.events.posts.store');
        });

        View::composer('member.community.index', function ($view): void {
            $tenant = $view->getData()['tenant'] ?? null;
            $events = $tenant ? Event::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'published')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')->limit(5)->get() : collect();
            $view->with('discussionEvents', $events);
        });
        View::composer('member.network-community.index', function ($view): void {
            $events = Event::query()->with('tenant')
                ->where('status', 'published')->where('visibility', 'public')->where('starts_at', '>=', now())
                ->whereHas('tenant', fn ($tenant) => $tenant->where('status', 'active')->networkListed())
                ->orderBy('starts_at')->limit(5)->get();
            $view->with('discussionEvents', $events);
        });

        CommunityPost::created(function (CommunityPost $post): void {
            try { app(CommunityMentionService::class)->tenantPost($post); } catch (\Throwable $exception) { report($exception); }
        });
        CommunityComment::created(function (CommunityComment $comment): void {
            try {
                $post = CommunityPost::query()->find($comment->post_id);
                if ($post) app(CommunityMentionService::class)->tenantComment($comment, $post);
            } catch (\Throwable $exception) { report($exception); }
        });
        NetworkCommunityPost::created(function (NetworkCommunityPost $post): void {
            try { app(CommunityMentionService::class)->networkPost($post); } catch (\Throwable $exception) { report($exception); }
        });
        NetworkCommunityComment::created(function (NetworkCommunityComment $comment): void {
            try {
                $post = NetworkCommunityPost::query()->find($comment->post_id);
                if ($post) app(CommunityMentionService::class)->networkComment($comment, $post);
            } catch (\Throwable $exception) { report($exception); }
        });
    }
}
