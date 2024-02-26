<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::any('/{path?}', [DashboardController::class, 'index'])
     ->where('path', '[\/\w\.-]*')
     ->where('path', '^(?!api).*$');

