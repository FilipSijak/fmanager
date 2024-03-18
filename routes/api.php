<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\InstanceController;
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
Route::get('/test/transactions', [\App\Http\Controllers\TestController::class, 'transactions']);
Route::get('/startNewGame', [\App\Http\Controllers\InstanceController::class, 'startNewGame']);
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
        'prefix' => 'instance'
    ],
    function () {
        Route::get('next-day', [InstanceController::class, 'nextDay']);
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
        Route::get('/{competitionId}/knockout-phase-round-view-data',
                   [CompetitionController::class, 'competitionKnockoutPhaseRoundViewData']
        );
        Route::get('/{competitionId}/knockout-phase-all-rounds',
                   [CompetitionController::class, 'competitionKnockoutPhaseAllRounds']
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

