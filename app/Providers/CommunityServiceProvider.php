<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Member\CommunityController;
use App\Http\Controllers\Member\GroupController;
use App\Http\Controllers\Member\LiveChatController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Middleware\EnsureTenantMember;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CommunityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', EnsureTenantMember::class])->group(function (): void {
            Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
            Route::post('/community/posts', [CommunityController::class, 'store'])->middleware('throttle:20,1')->name('community.posts.store');
            Route::delete('/community/posts/{post}', [CommunityController::class, 'destroy'])->name('community.posts.destroy');
            Route::patch('/community/posts/{post}/pin', [CommunityController::class, 'pin'])->name('community.posts.pin');
            Route::post('/community/posts/{post}/comments', [CommunityController::class, 'comment'])->middleware('throttle:40,1')->name('community.comments.store');
            Route::delete('/community/comments/{comment}', [CommunityController::class, 'destroyComment'])->name('community.comments.destroy');
            Route::post('/community/posts/{post}/reaction', [CommunityController::class, 'react'])->middleware('throttle:60,1')->name('community.reactions.store');
            Route::delete('/community/posts/{post}/reaction', [CommunityController::class, 'removeReaction'])->name('community.reactions.destroy');

            Route::get('/community/groups', [GroupController::class, 'index'])->name('community.groups.index');
            Route::post('/community/groups', [GroupController::class, 'store'])->name('community.groups.store');
            Route::get('/community/groups/{group}', [GroupController::class, 'show'])->whereNumber('group')->name('community.groups.show');
            Route::post('/community/groups/{group}/join', [GroupController::class, 'join'])->whereNumber('group')->name('community.groups.join');
            Route::delete('/community/groups/{group}/leave', [GroupController::class, 'leave'])->whereNumber('group')->name('community.groups.leave');
            Route::post('/community/groups/{group}/posts', [GroupController::class, 'post'])->whereNumber('group')->middleware('throttle:20,1')->name('community.groups.posts.store');
            Route::patch('/community/groups/{group}/members/{user}/approve', [GroupController::class, 'approve'])->whereNumber(['group', 'user'])->name('community.groups.members.approve');

            Route::get('/community/chat', [LiveChatController::class, 'index'])->name('community.chat');
            Route::get('/community/chat/poll', [LiveChatController::class, 'poll'])->middleware('throttle:120,1')->name('community.chat.poll');
            Route::post('/community/chat', [LiveChatController::class, 'store'])->middleware('throttle:60,1')->name('community.chat.store');
            Route::delete('/community/chat/{message}', [LiveChatController::class, 'destroy'])->name('community.chat.destroy');

            Route::get('/messages/{conversation}/poll', [MessageController::class, 'poll'])->middleware('throttle:120,1')->name('messages.poll');
            Route::patch('/messages/{conversation}/mute', [MessageController::class, 'mute'])->name('messages.mute');
        });
    }
}
