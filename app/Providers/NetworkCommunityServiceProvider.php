<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Member\NetworkCommunityController;
use App\Support\Edition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class NetworkCommunityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! Edition::isHosted()) {
            return;
        }

        Route::middleware(['web', 'auth'])->prefix('community/network')->name('network-community.')->group(function (): void {
            Route::get('/', [NetworkCommunityController::class, 'index'])->name('index');
            Route::post('/posts', [NetworkCommunityController::class, 'store'])->middleware('throttle:20,1')->name('posts.store');
            Route::delete('/posts/{post}', [NetworkCommunityController::class, 'destroy'])->name('posts.destroy');
            Route::patch('/posts/{post}/pin', [NetworkCommunityController::class, 'pin'])->name('posts.pin');
            Route::post('/posts/{post}/comments', [NetworkCommunityController::class, 'comment'])->middleware('throttle:40,1')->name('comments.store');
            Route::delete('/comments/{comment}', [NetworkCommunityController::class, 'destroyComment'])->name('comments.destroy');
            Route::post('/posts/{post}/reaction', [NetworkCommunityController::class, 'react'])->middleware('throttle:60,1')->name('reactions.store');
            Route::delete('/posts/{post}/reaction', [NetworkCommunityController::class, 'removeReaction'])->name('reactions.destroy');

            Route::get('/groups', [NetworkCommunityController::class, 'groups'])->name('groups.index');
            Route::post('/groups', [NetworkCommunityController::class, 'storeGroup'])->middleware('throttle:10,1')->name('groups.store');
            Route::get('/groups/{group}', [NetworkCommunityController::class, 'showGroup'])->whereNumber('group')->name('groups.show');
            Route::post('/groups/{group}/join', [NetworkCommunityController::class, 'joinGroup'])->whereNumber('group')->name('groups.join');
            Route::delete('/groups/{group}/leave', [NetworkCommunityController::class, 'leaveGroup'])->whereNumber('group')->name('groups.leave');
            Route::post('/groups/{group}/posts', [NetworkCommunityController::class, 'postGroup'])->whereNumber('group')->middleware('throttle:20,1')->name('groups.posts.store');
            Route::patch('/groups/{group}/members/{user}/approve', [NetworkCommunityController::class, 'approveGroupMember'])->whereNumber(['group', 'user'])->name('groups.members.approve');
        });
    }
}
