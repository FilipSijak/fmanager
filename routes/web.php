<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('start');

Route::get('/league-table', function () {
    return Inertia::render('LeagueTable');
})->name('league-table');

Route::get('/squad', function () {
    return Inertia::render('Squad');
})->name('squad');

Route::get('/player-profile', function () {
    return Inertia::render('PlayerProfile');
})->name('player-profile');

Route::get('/setup-game', function () {
    return Inertia::render('GameStart');
})->name('setup-game');
