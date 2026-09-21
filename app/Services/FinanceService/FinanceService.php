<?php

namespace App\Services\FinanceService;

use App\DataModels\ClubFinancialSummary;
use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\FinanceEntityLoanStatus;
use App\FinanceLoanType;
use App\GameEntityType;
use App\Models\Account;
use App\Models\AccountsDebtLinesEntity;
use App\Models\FinanceEntityLoan;
use App\Models\FinanceTransactionEntity;
use App\Models\FinanceTransactions;
use App\Models\Game;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStandConstruction;
use App\Repositories\ClubRepository;
use App\Services\FinanceService\Domain\CashLoanCalculator;
use App\Services\FinanceService\Domain\CashLoanEligibility;
use App\Services\FinanceService\Domain\CompetitionPrizeCalculator;
use App\Services\FinanceService\Domain\LoanTerms;
use App\Services\FinanceService\Domain\MortgageLoanCalculator;
use App\Services\FinanceService\Domain\TvRightsCalculator;
use App\Support\GameContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly GameContext $gameContext,
        private readonly CashLoanCalculator $cashLoanCalculator,
        private readonly CashLoanEligibility $cashLoanEligibility,
        private readonly MortgageLoanCalculator $mortgageLoanCalculator,
        private readonly TvRightsCalculator $tvRightsCalculator,
        private readonly CompetitionPrizeCalculator $competitionPrizeCalculator,
    ) {}

    public function getClubFinances(): ?ClubFinancialSummary
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());

        return $this->clubRepository->getTransferBudgetAndBalance($instance->club_id);
    }

    /**
     * @return Collection<int, FinanceEntityLoan>
     */
    public function getClubLoans(): Collection
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()
            ->where('club_id', $instance->club_id)
            ->firstOrFail();

        return FinanceEntityLoan::query()
            ->where('instance_id', $instance->id)
            ->where('borrower_club_account_id', $clubAccount->id)
            ->with('installments')
            ->latest('id')
            ->get();
    }

    public function takeOutCashLoan(
        int $amount,
        int $lengthMonths,
        CarbonInterface $startedAt,
        GameEntityType $lenderType = GameEntityType::BANK,
    ): FinanceEntityLoan {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()
            ->where('club_id', $instance->club_id)
            ->firstOrFail();
        $lenderAccount = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query) use ($lenderType): void {
                $query->where('type', $lenderType->value);
            })
            ->firstOrFail();
        if ($lengthMonths > $this->cashLoanMaximumLength($lenderType)) {
            throw new DomainException('The selected lender does not offer loans for that long.');
        }
        $terms = $this->cashLoanCalculator->calculate(
            $amount,
            $lengthMonths,
            $this->cashLoanInterestRate($lenderType),
        );

        return $this->issueLoan(
            $lenderAccount,
            $clubAccount,
            $terms,
            $startedAt,
        );
    }

    public function payForConstruction(int $amount, CarbonInterface $transactionDate): FinanceTransactionEntity
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()
            ->where('club_id', $instance->club_id)
            ->lockForUpdate()
            ->firstOrFail();
        if ($clubAccount->balance < $amount) {
            throw new DomainException('The club cannot afford this construction.');
        }
        $bankAccount = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query): void {
                $query->where('type', GameEntityType::BANK->value);
            })
            ->firstOrFail();

        return $this->makeEntityTransaction(
            $bankAccount,
            $clubAccount,
            EntityTransactionDirection::CLUB_TO_ENTITY,
            EntityTransactionType::STADIUM_CONSTRUCTION,
            $amount,
            $transactionDate,
        );
    }

    public function takeOutMortgageLoan(
        int $amount,
        int $lengthYears,
        CarbonInterface $startedAt,
        ?int $stadiumStandConstructionId = null,
        ?int $stadiumCommercialVenueId = null,
    ): FinanceEntityLoan {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()->where('club_id', $instance->club_id)->firstOrFail();
        $bankAccount = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query): void {
                $query->where('type', GameEntityType::BANK->value);
            })
            ->firstOrFail();

        return $this->issueLoan(
            $bankAccount,
            $clubAccount,
            $this->mortgageLoanCalculator->calculate($amount, $lengthYears),
            $startedAt,
            FinanceLoanType::MORTGAGE,
            false,
            $stadiumStandConstructionId,
            $stadiumCommercialVenueId,
        );
    }

    public function issueLoan(
        GameEntityAccount $lenderGameEntityAccount,
        Account $borrowerClubAccount,
        LoanTerms $terms,
        CarbonInterface $startedAt,
        FinanceLoanType $loanType = FinanceLoanType::CASH,
        bool $disbursePrincipal = true,
        ?int $stadiumStandConstructionId = null,
        ?int $stadiumCommercialVenueId = null,
    ): FinanceEntityLoan {
        $principal = $terms->principal;
        $interestAmount = $terms->interestAmount;
        $totalAmount = $terms->totalAmount;
        $installmentCount = $terms->installmentCount;

        return DB::transaction(function () use (
            $lenderGameEntityAccount,
            $borrowerClubAccount,
            $principal,
            $interestAmount,
            $totalAmount,
            $installmentCount,
            $terms,
            $startedAt,
            $loanType,
            $disbursePrincipal,
            $stadiumStandConstructionId,
            $stadiumCommercialVenueId,
        ): FinanceEntityLoan {
            $lockedClubAccount = Account::query()
                ->whereKey($borrowerClubAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasConstructionReference = (int) ($stadiumStandConstructionId !== null) + (int) ($stadiumCommercialVenueId !== null);
            if ($loanType === FinanceLoanType::MORTGAGE && $hasConstructionReference !== 1) {
                throw new DomainException('Mortgage loans must reference exactly one construction.');
            }
            if ($loanType === FinanceLoanType::MORTGAGE && $stadiumStandConstructionId !== null && ! StadiumStandConstruction::query()->whereKey($stadiumStandConstructionId)->where('instance_id', $lenderGameEntityAccount->instance_id)->exists()) {
                throw new DomainException('Mortgage construction does not belong to this instance.');
            }
            if ($loanType === FinanceLoanType::MORTGAGE && $stadiumStandConstructionId !== null && FinanceEntityLoan::query()->where('stadium_stand_construction_id', $stadiumStandConstructionId)->exists()) {
                throw new DomainException('This construction already has a mortgage loan.');
            }

            if ($loanType === FinanceLoanType::MORTGAGE && $stadiumCommercialVenueId !== null && ! StadiumCommercialVenue::query()->whereKey($stadiumCommercialVenueId)->where('instance_id', $lenderGameEntityAccount->instance_id)->exists()) {
                throw new DomainException('Mortgage construction does not belong to this instance.');
            }
            if ($loanType === FinanceLoanType::MORTGAGE && $stadiumCommercialVenueId !== null && FinanceEntityLoan::query()->where('stadium_commercial_venue_id', $stadiumCommercialVenueId)->exists()) {
                throw new DomainException('This construction already has a mortgage loan.');
            }

            if ($loanType !== FinanceLoanType::MORTGAGE && $hasConstructionReference !== 0) {
                throw new DomainException('Only mortgage loans may reference a construction.');
            }

            $this->cashLoanEligibility->ensureEligible($lockedClubAccount, $terms);

            $loan = FinanceEntityLoan::query()->create([
                'instance_id' => $lenderGameEntityAccount->instance_id,
                'lender_game_entity_account_id' => $lenderGameEntityAccount->id,
                'borrower_club_account_id' => $borrowerClubAccount->id,
                'loan_type' => $loanType,
                'stadium_stand_construction_id' => $stadiumStandConstructionId,
                'stadium_commercial_venue_id' => $stadiumCommercialVenueId,
                'principal' => $principal,
                'interest_amount' => $interestAmount,
                'total_amount' => $totalAmount,
                'installment_count' => $installmentCount,
                'started_at' => $startedAt->toDateString(),
                'status' => FinanceEntityLoanStatus::ACTIVE,
            ]);

            if ($disbursePrincipal) {
                $this->makeEntityTransaction(
                    $lenderGameEntityAccount,
                    $lockedClubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::LOAN,
                    $principal,
                    $startedAt,
                    $loan->id,
                );
            }

            $lenderGameEntityAccount->newQuery()
                ->whereKey($lenderGameEntityAccount->id)
                ->update(['future_balance' => DB::raw("future_balance + {$totalAmount}")]);
            $borrowerClubAccount->newQuery()
                ->whereKey($lockedClubAccount->id)
                ->update(['future_balance' => DB::raw("future_balance - {$totalAmount}")]);

            foreach ($terms->installmentAmounts as $index => $installmentAmount) {
                $installmentNumber = $index + 1;

                $loan->installments()->create([
                    'game_entity_account_id' => $lenderGameEntityAccount->id,
                    'club_account_id' => $borrowerClubAccount->id,
                    'amount' => $installmentAmount,
                    'created_at' => $startedAt->toDateString(),
                    'due_date' => $startedAt->copy()->addMonths($installmentNumber)->toDateString(),
                    'installment_number' => $installmentNumber,
                ]);
            }

            return $loan->load('installments');
        });
    }

    public function repayLoanInstallment(
        AccountsDebtLinesEntity $installment,
        CarbonInterface $paidAt,
    ): FinanceTransactionEntity {
        return DB::transaction(function () use ($installment, $paidAt): FinanceTransactionEntity {
            $lockedInstallment = AccountsDebtLinesEntity::query()
                ->whereKey($installment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInstallment->paid_at !== null) {
                throw new DomainException('This loan installment has already been paid.');
            }

            $loan = $lockedInstallment->loan()->lockForUpdate()->firstOrFail();

            $transaction = $this->recordEntityRepayment(
                GameEntityAccount::query()
                    ->whereKey($lockedInstallment->game_entity_account_id)
                    ->lockForUpdate()
                    ->firstOrFail(),
                Account::query()
                    ->whereKey($lockedInstallment->club_account_id)
                    ->lockForUpdate()
                    ->firstOrFail(),
                $lockedInstallment->amount,
                $paidAt,
                $loan->id,
            );

            $lockedInstallment->forceFill([
                'paid_at' => $paidAt,
                'transaction_id' => $transaction->id,
            ])->save();

            if (! $loan->installments()->whereNull('paid_at')->exists()) {
                $loan->update(['status' => FinanceEntityLoanStatus::COMPLETED]);
            }

            return $transaction;
        });
    }

    public function processDueLoanInstallments(Instance $instance, CarbonInterface $asOf): int
    {
        $processed = 0;

        AccountsDebtLinesEntity::query()
            ->whereNull('paid_at')
            ->whereDate('due_date', '<=', $asOf->toDateString())
            ->whereHas('loan', function (Builder $query) use ($instance): void {
                $query
                    ->where('instance_id', $instance->id)
                    ->where('status', FinanceEntityLoanStatus::ACTIVE);
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->each(function (AccountsDebtLinesEntity $installment) use (&$processed, $asOf): void {
                $this->repayLoanInstallment($installment, $asOf);
                $processed++;
            });

        return $processed;
    }

    public function makeEntityTransaction(
        GameEntityAccount $gameEntityAccount,
        Account $clubAccount,
        EntityTransactionDirection $direction,
        EntityTransactionType $eventType,
        int $amount,
        CarbonInterface $transactionDate,
        ?int $eventId = null,
    ): FinanceTransactionEntity {
        if ($amount <= 0) {
            throw new DomainException('Finance transaction amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $gameEntityAccount,
            $clubAccount,
            $direction,
            $eventType,
            $amount,
            $transactionDate,
            $eventId,
        ): FinanceTransactionEntity {
            $lockedEntityAccount = GameEntityAccount::query()
                ->whereKey($gameEntityAccount->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedClubAccount = Account::query()
                ->whereKey($clubAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($direction === EntityTransactionDirection::ENTITY_TO_CLUB) {
                $lockedEntityAccount->decrement('balance', $amount);
                $lockedEntityAccount->decrement('future_balance', $amount);
                $lockedClubAccount->increment('balance', $amount);
                $lockedClubAccount->increment('future_balance', $amount);
            } else {
                $lockedEntityAccount->increment('balance', $amount);
                $lockedEntityAccount->increment('future_balance', $amount);
                $lockedClubAccount->decrement('balance', $amount);
                $lockedClubAccount->decrement('future_balance', $amount);
            }

            return FinanceTransactionEntity::query()->create([
                'game_entity_account_id' => $lockedEntityAccount->id,
                'club_account_id' => $lockedClubAccount->id,
                'direction' => $direction,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'amount' => $amount,
                'transaction_date' => $transactionDate,
            ]);
        });
    }

    public function payTournamentTvRights(Instance $instance): void
    {
        $this->payTvRightsForCompetitionType($instance, 'tournament', Carbon::parse($instance->instance_date));
    }

    public function payLeagueTvRights(Instance $instance): void
    {
        $this->payTvRightsForCompetitionType($instance, 'league', Carbon::parse($instance->instance_date));
    }

    public function payLeagueCompetitionPrizes(Instance $instance): void
    {
        $rows = DB::table('competition_season AS cs')
            ->join('competitions AS competition', 'competition.id', '=', 'cs.competition_id')
            ->where('cs.instance_id', $instance->id)
            ->where('cs.season_id', $instance->season_id)
            ->where('competition.instance_id', $instance->id)
            ->where('competition.type', 'league')
            ->select(
                'cs.id AS membership_id',
                'cs.club_id',
                'competition.id AS competition_id',
                'competition.rank AS competition_rank',
            )
            ->orderBy('cs.id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $competitionIds = $rows->pluck('competition_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $competitionAccounts = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query) use ($competitionIds): void {
                $query->where('type', GameEntityType::COMPETITION->value)
                    ->whereIn('competition_id', $competitionIds);
            })
            ->with('gameEntity')
            ->get()
            ->keyBy(fn (GameEntityAccount $account): int => (int) $account->gameEntity->competition_id);
        $clubAccounts = Account::query()
            ->whereIn('club_id', $rows->pluck('club_id'))
            ->get()
            ->keyBy('club_id');

        DB::transaction(function () use ($rows, $competitionAccounts, $clubAccounts, $instance): void {
            foreach ($rows as $row) {
                DB::table('competition_season')
                    ->where('id', $row->membership_id)
                    ->lockForUpdate()
                    ->first();

                if (FinanceTransactionEntity::query()
                    ->where('event_type', EntityTransactionType::PRIZE->value)
                    ->where('event_id', $row->membership_id)
                    ->exists()) {
                    continue;
                }

                $clubAccount = $clubAccounts->get((int) $row->club_id);
                $competitionAccount = $competitionAccounts->get((int) $row->competition_id);
                if ($clubAccount === null || $competitionAccount === null) {
                    continue;
                }

                $amount = $this->competitionPrizeCalculator->calculateLeaguePrize((int) $row->competition_rank);
                if ($amount <= 0) {
                    continue;
                }

                $this->makeEntityTransaction(
                    $competitionAccount,
                    $clubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::PRIZE,
                    $amount,
                    Carbon::parse($instance->instance_date),
                    (int) $row->membership_id,
                );
            }
        });
    }

    public function payContinentalMatchPrizes(Game $game): void
    {
        $row = DB::table('games AS game')
            ->join('competitions AS competition', 'competition.id', '=', 'game.competition_id')
            ->leftJoin('tournament_knockout_ties AS tie', 'tie.id', '=', 'game.knockout_tie_id')
            ->leftJoin('tournament_knockout_rounds AS round', 'round.id', '=', 'tie.round_id')
            ->where('game.id', $game->id)
            ->where('game.status', Game::STATUS_COMPLETED)
            ->select(
                'game.*',
                'competition.rank AS competition_rank',
                'competition.type AS competition_type',
                'competition.competition_scope',
                'round.id AS round_id',
            )
            ->first();

        if ($row === null || $row->competition_type !== 'tournament' || $row->competition_scope !== 'continental') {
            return;
        }

        $competitionAccount = GameEntityAccount::query()
            ->where('instance_id', $row->instance_id)
            ->whereHas('gameEntity', function (Builder $query) use ($row): void {
                $query->where('type', GameEntityType::COMPETITION->value)
                    ->where('competition_id', $row->competition_id);
            })
            ->firstOrFail();

        foreach ([[$row->hometeam_id, 1], [$row->awayteam_id, 2]] as [$clubId, $resultCode]) {
            $membership = DB::table('competition_season')
                ->where('instance_id', $row->instance_id)
                ->where('season_id', $row->season_id)
                ->where('competition_id', $row->competition_id)
                ->where('club_id', $clubId)
                ->first();
            if ($membership === null) {
                continue;
            }

            $clubAccount = Account::query()->where('club_id', $clubId)->first();
            if ($clubAccount === null) {
                continue;
            }

            $result = (int) $row->winner === 3 ? 'draw' : ((int) $row->winner === $resultCode ? 'win' : 'loss');
            $matchAmount = $this->competitionPrizeCalculator->calculateContinentalMatchPrize(
                (int) $row->competition_rank,
                $result,
            );
            if ($matchAmount > 0 && ! FinanceTransactionEntity::query()
                ->where('event_type', EntityTransactionType::CONTINENTAL_MATCH_PRIZE->value)
                ->where('event_id', $row->id)
                ->where('club_account_id', $clubAccount->id)
                ->exists()) {
                $this->makeEntityTransaction(
                    $competitionAccount,
                    $clubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::CONTINENTAL_MATCH_PRIZE,
                    $matchAmount,
                    Carbon::parse($row->match_start ?? $game->processed_at ?? now()),
                    (int) $row->id,
                );
            }

            $roundEventId = $row->round_id === null ? (int) $membership->id : (int) $row->round_id;
            $roundComplete = $row->round_id !== null || ! DB::table('games')
                ->where('instance_id', $row->instance_id)
                ->where('season_id', $row->season_id)
                ->where('competition_id', $row->competition_id)
                ->whereNull('knockout_tie_id')
                ->where(function (QueryBuilder $query) use ($clubId): void {
                    $query->where('hometeam_id', $clubId)->orWhere('awayteam_id', $clubId);
                })
                ->whereNotIn('status', [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED, Game::STATUS_ABANDONED])
                ->exists();

            $roundAmount = $this->competitionPrizeCalculator->calculateContinentalRoundPrize((int) $row->competition_rank);
            if ($roundComplete && $roundAmount > 0 && ! FinanceTransactionEntity::query()
                ->where('event_type', EntityTransactionType::CONTINENTAL_ROUND_PRIZE->value)
                ->where('event_id', $roundEventId)
                ->where('club_account_id', $clubAccount->id)
                ->exists()) {
                $this->makeEntityTransaction(
                    $competitionAccount,
                    $clubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::CONTINENTAL_ROUND_PRIZE,
                    $roundAmount,
                    Carbon::parse($row->match_start ?? $game->processed_at ?? now()),
                    $roundEventId,
                );
            }
        }
    }

    public function payContinentalCompetitionPrizes(Instance $instance): void
    {
        $rows = DB::table('competition_season AS cs')
            ->join('competitions AS competition', 'competition.id', '=', 'cs.competition_id')
            ->where('cs.instance_id', $instance->id)
            ->where('cs.season_id', $instance->season_id)
            ->where('competition.instance_id', $instance->id)
            ->where('competition.type', 'tournament')
            ->where('competition.competition_scope', 'continental')
            ->select(
                'cs.id AS membership_id',
                'cs.club_id',
                'competition.id AS competition_id',
                'competition.rank AS competition_rank',
            )
            ->orderBy('cs.id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $competitionIds = $rows->pluck('competition_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $games = DB::table('games AS game')
            ->leftJoin('tournament_knockout_ties AS tie', 'tie.id', '=', 'game.knockout_tie_id')
            ->leftJoin('tournament_knockout_rounds AS round', 'round.id', '=', 'tie.round_id')
            ->where('game.instance_id', $instance->id)
            ->where('game.season_id', $instance->season_id)
            ->whereIn('game.competition_id', $competitionIds)
            ->where('game.status', Game::STATUS_COMPLETED)
            ->select([
                'game.competition_id',
                'game.hometeam_id',
                'game.awayteam_id',
                'game.winner',
                'round.id AS round_id',
            ])
            ->get();

        $participation = [];
        foreach ($games as $game) {
            $roundKey = $game->round_id === null ? 'group' : 'knockout:'.(int) $game->round_id;
            foreach ([[$game->hometeam_id, 1], [$game->awayteam_id, 2]] as [$clubId, $result]) {
                $key = (int) $game->competition_id.':'.(int) $clubId;
                $participation[$key] ??= ['rounds' => [], 'wins' => 0, 'draws' => 0];
                $participation[$key]['rounds'][$roundKey] = true;

                if ((int) $game->winner === 3) {
                    $participation[$key]['draws']++;
                } elseif ((int) $game->winner === $result) {
                    $participation[$key]['wins']++;
                }
            }
        }

        $competitionAccounts = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query) use ($competitionIds): void {
                $query->where('type', GameEntityType::COMPETITION->value)
                    ->whereIn('competition_id', $competitionIds);
            })
            ->with('gameEntity')
            ->get()
            ->keyBy(fn (GameEntityAccount $account): int => (int) $account->gameEntity->competition_id);
        $clubAccounts = Account::query()
            ->whereIn('club_id', $rows->pluck('club_id'))
            ->get()
            ->keyBy('club_id');

        DB::transaction(function () use ($rows, $participation, $competitionAccounts, $clubAccounts, $instance): void {
            foreach ($rows as $row) {
                DB::table('competition_season')
                    ->where('id', $row->membership_id)
                    ->lockForUpdate()
                    ->first();

                if (FinanceTransactionEntity::query()
                    ->where('event_type', EntityTransactionType::PRIZE->value)
                    ->where('event_id', $row->membership_id)
                    ->exists()) {
                    continue;
                }

                $stats = $participation[(int) $row->competition_id.':'.(int) $row->club_id] ?? null;
                $competitionAccount = $competitionAccounts->get((int) $row->competition_id);
                $clubAccount = $clubAccounts->get((int) $row->club_id);
                if ($stats === null || $competitionAccount === null || $clubAccount === null) {
                    continue;
                }

                $amount = $this->competitionPrizeCalculator->calculateContinentalPrize(
                    (int) $row->competition_rank,
                    count($stats['rounds']),
                    $stats['wins'],
                    $stats['draws'],
                );
                if ($amount <= 0) {
                    continue;
                }

                $this->makeEntityTransaction(
                    $competitionAccount,
                    $clubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::PRIZE,
                    $amount,
                    Carbon::parse($instance->instance_date),
                    (int) $row->membership_id,
                );
            }
        });
    }

    private function payTvRightsForCompetitionType(
        Instance $instance,
        string $competitionType,
        CarbonInterface $transactionDate,
    ): void {
        $rows = DB::table('competition_season AS cs')
            ->join('competitions AS competition', 'competition.id', '=', 'cs.competition_id')
            ->join('clubs AS club', 'club.id', '=', 'cs.club_id')
            ->where('cs.instance_id', $instance->id)
            ->where('cs.season_id', $instance->season_id)
            ->where('competition.instance_id', $instance->id)
            ->where('competition.type', $competitionType)
            ->select(
                'cs.id AS membership_id',
                'cs.club_id',
                'cs.played',
                'competition.id AS competition_id',
                'competition.rank AS competition_rank',
                'competition.clubs_number',
                'competition.groups',
                'competition.type AS competition_type',
                'club.rank AS club_rank',
            )
            ->orderBy('cs.id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $competitionIds = $rows->pluck('competition_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $competitionAccounts = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query) use ($competitionIds): void {
                $query->where('type', GameEntityType::COMPETITION->value)
                    ->whereIn('competition_id', $competitionIds);
            })
            ->with('gameEntity')
            ->get()
            ->keyBy(fn (GameEntityAccount $account): int => (int) $account->gameEntity->competition_id);
        $clubAccounts = Account::query()
            ->whereIn('club_id', $rows->pluck('club_id'))
            ->get()
            ->keyBy('club_id');

        DB::transaction(function () use ($rows, $competitionAccounts, $clubAccounts, $transactionDate): void {
            foreach ($rows as $row) {
                DB::table('competition_season')
                    ->where('id', $row->membership_id)
                    ->lockForUpdate()
                    ->first();

                if (FinanceTransactionEntity::query()
                    ->where('event_type', EntityTransactionType::TV_REVENUE->value)
                    ->where('event_id', $row->membership_id)
                    ->exists()) {
                    continue;
                }

                $clubAccount = $clubAccounts->get((int) $row->club_id);
                if ($clubAccount === null) {
                    continue;
                }

                $competitionAccount = $competitionAccounts->get((int) $row->competition_id);
                if ($competitionAccount === null) {
                    throw new \LogicException("No finance account exists for competition {$row->competition_id}.");
                }

                $amount = $this->tvRightsCalculator->calculateForCompetition(
                    (int) $row->competition_rank,
                    (int) $row->club_rank,
                    (string) $row->competition_type,
                    (int) $row->clubs_number,
                    $row->groups === null ? null : (int) $row->groups,
                    (int) $row->played,
                );

                if ($amount <= 0) {
                    continue;
                }

                $this->makeEntityTransaction(
                    $competitionAccount,
                    $clubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::TV_REVENUE,
                    $amount,
                    $transactionDate,
                    (int) $row->membership_id,
                );
            }
        });
    }

    public function makeTransaction(
        Account $receivingAccount,
        Account $sendingAccount,
        int $amount,
    ): bool {
        try {
            DB::beginTransaction();

            // log transaction
            $transaction = new FinanceTransactions([
                'sending_account_id' => $sendingAccount->id,
                'receiving_account_id' => $receivingAccount->id,
                'amount' => $amount,
                'transaction_date' => Carbon::today()->toDateString(),
            ]);

            // move amount
            $sendingAccount->balance -= $amount;
            $sendingAccount->future_balance -= $amount;
            $receivingAccount->balance += $amount;
            $receivingAccount->future_balance += $amount;

            $receivingAccount->save();
            $sendingAccount->save();
            $transaction->save();
        } catch (\Exception $e) {
            DB::rollBack();

            // log error

            return false;
        }

        DB::commit();

        return true;
    }

    private function cashLoanInterestRate(GameEntityType $lenderType): float
    {
        return match ($lenderType) {
            GameEntityType::BANK => 0.08,
            GameEntityType::LOAN_SHARKS => 0.15,
            default => throw new DomainException('The selected entity does not offer cash loans.'),
        };
    }

    private function cashLoanMaximumLength(GameEntityType $lenderType): int
    {
        return match ($lenderType) {
            GameEntityType::BANK => 36,
            GameEntityType::LOAN_SHARKS => 24,
            default => throw new DomainException('The selected entity does not offer cash loans.'),
        };
    }

    private function recordEntityRepayment(
        GameEntityAccount $entityAccount,
        Account $clubAccount,
        int $amount,
        CarbonInterface $paidAt,
        int $loanId,
    ): FinanceTransactionEntity {
        $entityAccount->increment('balance', $amount);
        $clubAccount->decrement('balance', $amount);

        return FinanceTransactionEntity::query()->create([
            'game_entity_account_id' => $entityAccount->id,
            'club_account_id' => $clubAccount->id,
            'direction' => EntityTransactionDirection::CLUB_TO_ENTITY,
            'event_type' => EntityTransactionType::LOAN_REPAYMENT,
            'event_id' => $loanId,
            'amount' => $amount,
            'transaction_date' => $paidAt,
        ]);
    }
}
