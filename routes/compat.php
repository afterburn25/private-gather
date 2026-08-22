<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public route compatibility aliases
|--------------------------------------------------------------------------
|
| Keep legacy/public route names available while the underlying navigation
| evolves. Blade templates, cached views, upgrade overlays, and bookmarks may
| retain these names across releases; removing one must not turn a public page
| render into an HTTP 500.
|
*/

if (! Route::has('clubs.index')) {
    Route::redirect('/clubs', '/organizations', 302)->name('clubs.index');
}
