<?php

use App\Http\Controllers\Api\ClubController;
use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\FinanceLoanController;
use App\Http\Controllers\Api\FinanceSponsorController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\LiveMatchController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PlayerSearchController;
use App\Http\Controllers\Api\SponsorshipOfferController;
use App\Http\Controllers\Api\SquadLineupController;
use App\Http\Controllers\Api\StadiumController;
use App\Http\Controllers\Api\TacticsController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\TransferManagementController;
use App\Http\Middleware\EnsureGameIsValid;
use App\Http\Middleware\EnsureUserHasClub;
use App\Http\Middleware\SetGameContext;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/startNewGame', [InstanceController::class, 'startNewGame'])->middleware('throttle:3,1');

    Route::middleware([SetGameContext::class, EnsureGameIsValid::class])->group(function () {
        Route::post('/instance/next-day', [InstanceController::class, 'nextDay']);

        Route::get('/news', [NewsController::class, 'index']);
        Route::post('/news/{newsId}/read', [NewsController::class, 'markAsRead']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::middleware(EnsureUserHasClub::class)->group(function (): void {
            Route::post('/transfers', [TransferManagementController::class, 'store'])->name('transfers.store');
            Route::post('/transfers/{transferId}/respond', [TransferManagementController::class, 'respond'])->whereNumber('transferId')->name('transfers.respond');
            Route::post('/instance/resign', [InstanceController::class, 'resign']);
            Route::get('/finance', [FinanceController::class, 'show']);
            Route::get('/finance/loans', [FinanceLoanController::class, 'index']);
            Route::get('/finance/sponsors', [FinanceSponsorController::class, 'index']);
            Route::get('/finance/sponsor-offers', [SponsorshipOfferController::class, 'index']);
            Route::get('/finance/sponsor-offers/history', [SponsorshipOfferController::class, 'history']);
            Route::post('/finance/sponsor-offers/{offerId}/accept', [SponsorshipOfferController::class, 'accept'])->whereNumber('offerId');
            Route::post('/finance/sponsor-offers/{offerId}/reject', [SponsorshipOfferController::class, 'reject'])->whereNumber('offerId');
            Route::post('/finance/sponsor-offers/{offerId}/negotiate', [SponsorshipOfferController::class, 'negotiate'])->whereNumber('offerId');
            Route::post('/finance/loans', [FinanceLoanController::class, 'store']);

            Route::prefix('stadium')->group(function (): void {
                Route::get('/', [StadiumController::class, 'show']);
                Route::get('/commercial-categories', [StadiumController::class, 'buildableCommercialCategories']);
                Route::get('/stands/{standId}/expansion-cost', [StadiumController::class, 'standExpansionCost']);
                Route::post('/construction', [StadiumController::class, 'build']);
                Route::delete('/commercial-venues/{venueId}', [StadiumController::class, 'demolishCommercialVenue']);
            });

            Route::prefix('tactics')->name('tactics.')->group(function (): void {
                Route::get('/', [TacticsController::class, 'show'])->name('show');
                Route::put('/', [TacticsController::class, 'update'])->name('update');
            });

            Route::prefix('live-match/{gameId}')->whereNumber('gameId')->name('live-match.')->group(function (): void {
                Route::get('/', [LiveMatchController::class, 'show'])->name('show');
                Route::post('/advance', [LiveMatchController::class, 'advance'])->name('advance');
                Route::post('/substitutions', [LiveMatchController::class, 'substitute'])->name('substitute');
                Route::put('/tactics', [LiveMatchController::class, 'tactics'])->name('tactics');
            });

            Route::prefix('squad')->group(function (): void {
                Route::get('/lineup', [SquadLineupController::class, 'show']);
                Route::post('/lineup', [SquadLineupController::class, 'store']);
            });
        });

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
