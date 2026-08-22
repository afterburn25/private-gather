<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Member\CommunityController;
use App\Http\Controllers\Member\EventCommunityController;
use App\Http\Controllers\Member\GroupController;
use App\Http\Controllers\Member\HostedMatchController;
use App\Http\Controllers\Member\HostedNetworkController;
use App\Http\Controllers\Member\LiveChatController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Controllers\Member\NotificationController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\RewardController;
use App\Http\Controllers\Tenant\ClubNewsController;
use App\Http\Controllers\Tenant\CommunityMemberController;
use App\Http\Middleware\EnsureDiscoverableConnectionTarget;
use App\Http\Middleware\EnsureJoinableMemberGroup;
use App\Http\Middleware\EnsureTenantManager;
use App\Http\Middleware\EnsureTenantMember;
use App\Support\Edition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CommunityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/news', [ClubNewsController::class, 'publicIndex'])->name('club.news.index');
            Route::get('/news/{post}', [ClubNewsController::class, 'publicShow'])->whereNumber('post')->name('club.news.show');
        });

        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::post('/profile/media', [ProfileController::class, 'uploadMedia'])->middleware('throttle:10,1')->name('profile.media');
            Route::get('/profile/media/{kind}', [ProfileController::class, 'ownMedia'])->name('profile.media.show');
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::patch('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        });

        Route::middleware(['web', 'auth', EnsureTenantMember::class])->group(function (): void {
            Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
            Route::post('/community/posts', [CommunityController::class, 'store'])->middleware('throttle:20,1')->name('community.posts.store');
            Route::get('/community/posts/{post}/media', [CommunityController::class, 'media'])->name('community.posts.media');
            Route::delete('/community/posts/{post}', [CommunityController::class, 'destroy'])->name('community.posts.destroy');
            Route::patch('/community/posts/{post}/pin', [CommunityController::class, 'pin'])->name('community.posts.pin');
            Route::post('/community/posts/{post}/comments', [CommunityController::class, 'comment'])->middleware('throttle:40,1')->name('community.comments.store');
            Route::delete('/community/comments/{comment}', [CommunityController::class, 'destroyComment'])->name('community.comments.destroy');
            Route::post('/community/posts/{post}/reaction', [CommunityController::class, 'react'])->middleware('throttle:60,1')->name('community.reactions.store');
            Route::delete('/community/posts/{post}/reaction', [CommunityController::class, 'removeReaction'])->name('community.reactions.destroy');
            Route::post('/community/posts/{post}/vote', [CommunityController::class, 'vote'])->middleware('throttle:60,1')->name('community.polls.vote');

            Route::get('/community/chat', [LiveChatController::class, 'index'])->name('community.chat');
            Route::get('/community/chat/poll', [LiveChatController::class, 'poll'])->middleware('throttle:120,1')->name('community.chat.poll');
            Route::post('/community/chat', [LiveChatController::class, 'store'])->middleware('throttle:60,1')->name('community.chat.store');
            Route::delete('/community/chat/{message}', [LiveChatController::class, 'destroy'])->name('community.chat.destroy');

            Route::get('/members/{user}', [ProfileController::class, 'show'])->whereNumber('user')->name('members.show');
            Route::get('/members/{user}/media/{kind}', [ProfileController::class, 'media'])->whereNumber('user')->name('members.media');
            Route::post('/profile/partner-invite', [ProfileController::class, 'invitePartner'])->name('profile.partner.invite');
            Route::post('/profile/partner-invites/{invite}/respond', [ProfileController::class, 'respondPartner'])->whereNumber('invite')->name('profile.partner.respond');
            Route::delete('/profile/partner', [ProfileController::class, 'unlinkPartner'])->name('profile.partner.unlink');
            Route::post('/members/{user}/block', [ProfileController::class, 'block'])->whereNumber('user')->name('members.block');
            Route::delete('/members/{user}/block', [ProfileController::class, 'unblock'])->whereNumber('user')->name('members.unblock');

            Route::get('/messages/{conversation}/poll', [MessageController::class, 'poll'])->middleware('throttle:120,1')->name('messages.poll');
            Route::post('/messages/{conversation}/typing', [MessageController::class, 'typing'])->middleware('throttle:120,1')->name('messages.typing');
            Route::get('/message-media/{message}', [MessageController::class, 'attachment'])->name('messages.attachment');
            Route::patch('/messages/{conversation}/mute', [MessageController::class, 'mute'])->name('messages.mute');

            Route::get('/events/{event}/community', [EventCommunityController::class, 'show'])->whereNumber('event')->name('events.community');
            Route::post('/events/{event}/community', [EventCommunityController::class, 'store'])->whereNumber('event')->middleware('throttle:20,1')->name('events.community.store');

            Route::get('/rewards', [RewardController::class, 'index'])->name('rewards.index');
            Route::post('/rewards/{reward}/redeem', [RewardController::class, 'redeem'])->whereNumber('reward')->name('rewards.redeem');

            Route::get('/groups/{group}', [GroupController::class, 'show'])->whereNumber('group')->name('groups.show');
            Route::post('/groups/{group}/posts', [GroupController::class, 'storePost'])->whereNumber('group')->middleware('throttle:20,1')->name('groups.posts.store');
            Route::post('/groups/{group}/invites', [GroupController::class, 'invite'])->whereNumber('group')->name('groups.invites.store');
            Route::post('/groups/invites/{invite}/respond', [GroupController::class, 'respondInvite'])->whereNumber('invite')->name('groups.invites.respond');

            if (Edition::isHosted()) {
                Route::get('/network', [HostedNetworkController::class, 'index'])->name('network.index');
                Route::post('/network/connections/{user}', [HostedNetworkController::class, 'requestConnection'])->middleware([EnsureDiscoverableConnectionTarget::class, 'throttle:30,1'])->name('network.connections.request');
                Route::patch('/network/connections/{connection}/accept', [HostedNetworkController::class, 'acceptConnection'])->name('network.connections.accept');
                Route::delete('/network/connections/{connection}', [HostedNetworkController::class, 'removeConnection'])->name('network.connections.remove');

                Route::get('/matches', [HostedMatchController::class, 'index'])->name('matches.index');
                Route::post('/matches/likes/{user}', [HostedMatchController::class, 'like'])->middleware([EnsureDiscoverableConnectionTarget::class, 'throttle:60,1'])->name('matches.likes.store');
                Route::delete('/matches/likes/{user}', [HostedMatchController::class, 'unlike'])->name('matches.likes.destroy');

                Route::post('/network/albums', [HostedNetworkController::class, 'createAlbum'])->name('network.albums.create');
                Route::post('/network/albums/{album}/photos', [HostedNetworkController::class, 'uploadAlbumPhoto'])->middleware('throttle:20,1')->name('network.albums.photos.store');
                Route::get('/network/photos/{photo}', [HostedNetworkController::class, 'showAlbumPhoto'])->name('network.albums.photos.show');
                Route::post('/network/albums/{album}/grants', [HostedNetworkController::class, 'grantAlbum'])->name('network.albums.grants.store');
                Route::delete('/network/albums/{album}/grants/{grant}', [HostedNetworkController::class, 'revokeAlbum'])->name('network.albums.grants.revoke');

                Route::post('/network/groups', [HostedNetworkController::class, 'createGroup'])->name('network.groups.create');
                Route::post('/network/groups/{group}/join', [HostedNetworkController::class, 'joinGroup'])->middleware(EnsureJoinableMemberGroup::class)->name('network.groups.join');
                Route::delete('/network/groups/{group}/leave', [HostedNetworkController::class, 'leaveGroup'])->name('network.groups.leave');

                Route::post('/network/travel', [HostedNetworkController::class, 'createTravelPlan'])->name('network.travel.create');
                Route::delete('/network/travel/{plan}', [HostedNetworkController::class, 'deleteTravelPlan'])->name('network.travel.delete');
            }
        });

        Route::middleware(['web','auth',EnsureTenantManager::class])->prefix('manage/community')->name('tenant.community.')->group(function (): void {
            Route::get('/members', [CommunityMemberController::class, 'index'])->name('members');
            Route::patch('/applications/{application}', [CommunityMemberController::class, 'review'])->name('applications.review');
            Route::patch('/members/{user}/status', [CommunityMemberController::class, 'status'])->name('members.status');
            Route::post('/members/{user}/badges', [CommunityMemberController::class, 'badge'])->name('members.badges');
            Route::post('/members/{user}/points', [CommunityMemberController::class, 'awardPoints'])->name('members.points');
            Route::post('/member-card/verify', [CommunityMemberController::class, 'verifyMemberCard'])->middleware('throttle:60,1')->name('member-card.verify');
            Route::post('/rewards', [CommunityMemberController::class, 'createReward'])->name('rewards.store');
            Route::patch('/redemptions/{redemption}', [CommunityMemberController::class, 'redemption'])->name('redemptions.update');
            Route::patch('/reports/{report}', [CommunityMemberController::class, 'report'])->name('reports.update');
            Route::patch('/settings', [CommunityMemberController::class, 'settings'])->name('settings');
            Route::get('/news', [ClubNewsController::class, 'manage'])->name('news');
            Route::post('/news', [ClubNewsController::class, 'store'])->name('news.store');
            Route::patch('/news/{post}', [ClubNewsController::class, 'update'])->name('news.update');
            Route::delete('/news/{post}', [ClubNewsController::class, 'destroy'])->name('news.destroy');
        });
    }
}
