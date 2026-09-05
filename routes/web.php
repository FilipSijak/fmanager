<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('start');

Route::get('/league-table', function () {
    return Inertia::render('LeagueTable');
})->name('league-table');
