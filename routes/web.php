<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetitionPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\ManagerJobController;
use App\Http\Middleware\EnsureGameIsValid;
use App\Http\Middleware\EnsureUserHasClub;
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
    Route::post('/instances/{instanceId}/take-over', [InstanceController::class, 'takeOver'])->name('instances.take-over');

    Route::middleware([SetGameContext::class, EnsureGameIsValid::class])->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('start');
        Route::get('/competitions', [CompetitionPageController::class, 'index'])->name('competitions.index');
        Route::get('/competitions/{competitionId}', [CompetitionPageController::class, 'show'])->whereNumber('competitionId')->name('competitions.show');
        Route::get('/league-table', [CompetitionPageController::class, 'index'])->name('league-table');
        Route::get('/jobs', [ManagerJobController::class, 'index'])->name('jobs');
        Route::post('/jobs/{clubId}/apply', [ManagerJobController::class, 'apply'])->name('jobs.apply');
    });
});

$templateRoutes = function () {
    Route::get('/player-profile/{playerId}', fn (int $playerId) => Inertia::render('player-profile/PlayerProfile', ['playerId' => $playerId]))->name('player-profile');

    Route::middleware(EnsureUserHasClub::class)->group(function (): void {
        Route::get('/squad', fn () => Inertia::render('squad/Squad'))->name('squad');
        Route::get('/tactics', fn () => Inertia::render('tactics/Tactics'))->name('tactics');
        Route::get('/office', fn () => Inertia::render('Office'))->name('office');
        Route::get('/finance', fn () => Inertia::render('Finance'))->name('finance');
        Route::get('/stadium', fn () => Inertia::render('Stadium'))->name('stadium');
        Route::get('/stadium/construction', fn () => Inertia::render('stadium-construction/StadiumConstruction'))->name('stadium.construction');
        Route::get('/stadium/restaurants', fn () => Inertia::render('StadiumRestaurants'))->name('stadium.restaurants');
        Route::get('/stadium/bars', fn () => Inertia::render('StadiumBars'))->name('stadium.bars');
        Route::get('/stadium/shops', fn () => Inertia::render('StadiumShops'))->name('stadium.shops');
    });
};

if (env('ENVIRONMENT') === 'dev') {
    // Static-mock template pages, login-free so they can be previewed without a game account.
    $templateRoutes();
} else {
    Route::middleware(['auth', SetGameContext::class, EnsureGameIsValid::class])->group($templateRoutes);
}
