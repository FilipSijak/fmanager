<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\PlayerController;
use App\Http\Middleware\EnsureGameIsValid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);
Route::get('/setNewGame', [\App\Http\Controllers\TestController::class, 'setNewGame']);
/*Route::middleware('auth:sanctum')->get('/test', function (Request $request) {
    Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);
    return $request->user();
});*/

Route::group(
    [
        'prefix' => 'club'
    ],
    function () {
        Route::get('/{club}', [ClubController::class, 'show']);
    }
);

Route::group(
    [
        'prefix' => 'player'
    ],
    function () {
        Route::get('/{playerId}', [PlayerController::class, 'show']);
    }
);

Route::group(
    [
        'prefix' => 'competition'
    ],
    function () {
        Route::get('/{competitionId}', [CompetitionController::class, 'show']);
        Route::get('/{competitionId}/table', [CompetitionController::class, 'competitionTable']);
        Route::get('/{competitionId}/tournament-groups-tables',
           [CompetitionController::class, 'tournamentGroupsTables']
        );
        Route::get('/{competitionId}/knockout-phase',
            [CompetitionController::class, 'competitionKnockoutPhase']
        );
    }
);

/*Route::middleware([EnsureGameIsValid::class])->group(function () {
    Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);
});*/

Route::get('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

