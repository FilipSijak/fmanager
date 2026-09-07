<?php

use App\Http\Controllers\ClubController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerSearchController;
use App\Http\Controllers\TransferController;
use App\Http\Middleware\EnsureGameIsValid;
use App\Http\Middleware\SetGameContext;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/startNewGame', [InstanceController::class, 'startNewGame'])->middleware('throttle:3,1');

    Route::middleware([SetGameContext::class, EnsureGameIsValid::class])->group(function () {
        Route::post('/instance/next-day', [InstanceController::class, 'nextDay']);

        Route::get('/news', [NewsController::class, 'index']);
        Route::post('/news/{newsId}/read', [NewsController::class, 'markAsRead']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::post('/game/{gameId}/complete', [GameController::class, 'complete']);
        Route::post('/game/{gameId}/postpone', [GameController::class, 'postpone']);
        Route::post('/game/{gameId}/cancel', [GameController::class, 'cancel']);

        Route::group(
            [
                'prefix' => 'club',
            ],
            function () {
                Route::get('/{clubId}/squad', [ClubController::class, 'squad']);
                Route::get('/{clubId}', [ClubController::class, 'show']);
            }
        );

        Route::group(
            [
                'prefix' => 'player',
            ],
            function () {
                Route::get('/search', PlayerSearchController::class);
                Route::get('/{playerId}', [PlayerController::class, 'show']);
            }
        );

        Route::group(
            [
                'prefix' => 'competition',
            ],
            function () {
                Route::get('/{competitionId}', [CompetitionController::class, 'show']);
                Route::get('/{competitionId}/table', [CompetitionController::class, 'competitionTable']);
                Route::get('/{competitionId}/movement-preview', [CompetitionController::class, 'movementPreview']);
                Route::get('/{competitionId}/qualification-preview', [CompetitionController::class, 'qualificationPreview']);
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

        Route::group(
            [
                'prefix' => 'transfer',
            ],
            function () {
                Route::post('/free-transfer', [TransferController::class, 'makeFreeTransferRequest']);
                Route::post('/', [TransferController::class, 'makeTransferRequest']);
            }
        );
    });

});
