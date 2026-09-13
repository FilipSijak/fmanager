<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstanceController;
use App\Http\Middleware\EnsureGameIsValid;
use App\Http\Middleware\SetGameContext;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth', ['registering' => false]))->name('login');
    Route::get('/register', fn () => Inertia::render('Auth', ['registering' => true]))->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/setup-game', [InstanceController::class, 'setup'])->name('setup-game');
    Route::post('/instances/{instanceId}/select', [InstanceController::class, 'select'])->name('instances.select');

    Route::middleware([SetGameContext::class, EnsureGameIsValid::class])->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('start');
    });
});

$templateRoutes = function () {
    Route::get('/league-table', fn () => Inertia::render('LeagueTable'))->name('league-table');
    Route::get('/squad', fn () => Inertia::render('Squad'))->name('squad');
    Route::get('/player-profile', fn () => Inertia::render('PlayerProfile'))->name('player-profile');
    Route::get('/tactics', fn () => Inertia::render('Tactics'))->name('tactics');
    Route::get('/office', fn () => Inertia::render('Office'))->name('office');
    Route::get('/stadium', fn () => Inertia::render('Stadium'))->name('stadium');
    Route::get('/stadium/construction', fn () => Inertia::render('StadiumConstruction'))->name('stadium.construction');
    Route::get('/stadium/restaurants', fn () => Inertia::render('StadiumRestaurants'))->name('stadium.restaurants');
    Route::get('/stadium/bars', fn () => Inertia::render('StadiumBars'))->name('stadium.bars');
    Route::get('/stadium/shops', fn () => Inertia::render('StadiumShops'))->name('stadium.shops');
};

if (env('ENVIRONMENT') === 'dev') {
    // Static-mock template pages, login-free so they can be previewed without a game account.
    $templateRoutes();
} else {
    Route::middleware(['auth', SetGameContext::class, EnsureGameIsValid::class])->group($templateRoutes);
}
