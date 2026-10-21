<?php

namespace App\Services\FinanceService;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\GameEntityType;
use App\Models\Account;
use App\Models\Club;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipPayment;
use App\Services\FinanceService\Domain\SponsorshipOfferCalculator;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class SponsorshipService
{
    public function __construct(
        private readonly FinanceService $finances,
        private readonly SponsorshipOfferCalculator $calculator,
        private readonly GameContext $context,
    ) {}

    public function createInitialContracts(Instance $instance): void
    {
        $season = Season::query()->where('instance_id', $instance->id)->findOrFail($instance->season_id);
        $startsAt = CarbonImmutable::parse($season->start_date);
        $firstSeasonEnd = CarbonImmutable::parse($season->end_date);
        $sponsorAccounts = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', fn (Builder $query) => $query->where('instance_id', $instance->id)->where('type', GameEntityType::SPONSOR->value))
            ->with('gameEntity')
            ->get();
        if ($sponsorAccounts->isEmpty()) {
            throw new LogicException('No sponsors are available for this game. Seed the game entity catalogue before creating a game.');
        }

        Club::query()->forInstance($instance->id)->each(function (Club $club) use ($instance, $startsAt, $firstSeasonEnd, $sponsorAccounts): void {
            $eligibleSponsors = $sponsorAccounts->filter(fn (GameEntityAccount $account): bool => $this->calculator->isEligible((int) $club->rank, $account->gameEntity->rank));
            if ($eligibleSponsors->isEmpty()) {
                return;
            }
            $nearestRankDistance = $eligibleSponsors->min(fn (GameEntityAccount $account): int => abs($account->gameEntity->rank - (int) $club->rank));
            $sponsorAccount = $eligibleSponsors->filter(fn (GameEntityAccount $account): bool => abs($account->gameEntity->rank - (int) $club->rank) === $nearestRankDistance)->random();
            SponsorshipContract::query()->firstOrCreate([
                'club_id' => $club->id,
                'starts_at' => $startsAt->toDateString(),
            ], [
                'instance_id' => $instance->id,
                'sponsor_account_id' => $sponsorAccount->id,
                'annual_amount' => $this->calculator->initialAmount((int) $club->rank, $sponsorAccount->gameEntity->rank),
                'expires_at' => $firstSeasonEnd->addYearsNoOverflow(random_int(0, 4))->toDateString(),
            ]);
        });
    }

    /** @return Collection<int, SponsorshipContract> */
    public function getClubContracts(): Collection
    {
        $instance = Instance::query()->findOrFail($this->context->instanceId());

        return SponsorshipContract::query()
            ->forInstance($instance->id)
            ->where('club_id', $instance->club_id)
            ->with('sponsorAccount.gameEntity')
            ->withSum(['payments as paid_this_season' => fn (Builder $query) => $query->where('season_id', $this->context->seasonId())], 'amount')
            ->orderByDesc('expires_at')
            ->orderBy('id')
            ->get();
    }

    public function payForSeason(Instance $instance): void
    {
        $season = Season::query()->where('instance_id', $instance->id)->findOrFail($instance->season_id);
        $date = CarbonImmutable::parse($instance->instance_date)->toDateString();
        if ($date !== $season->start_date) {
            return;
        }

        SponsorshipContract::query()->forInstance($instance->id)
            ->whereHas('club', fn (Builder $query) => $query->where('instance_id', $instance->id))
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('expires_at', '>=', $date)
            ->where('annual_amount', '>', 0)
            ->each(function (SponsorshipContract $contract) use ($instance, $date): void {
                DB::transaction(function () use ($contract, $instance, $date): void {
                    $lockedContract = SponsorshipContract::query()->lockForUpdate()->findOrFail($contract->id);
                    if ($lockedContract->payments()->where('season_id', $instance->season_id)->exists()) {
                        return;
                    }

                    $sponsorAccount = GameEntityAccount::query()
                        ->where('instance_id', $instance->id)
                        ->whereHas('gameEntity', fn (Builder $query) => $query->where('instance_id', $instance->id)->where('type', GameEntityType::SPONSOR->value))
                        ->findOrFail($lockedContract->sponsor_account_id);
                    $clubAccount = Account::query()->where('club_id', $lockedContract->club_id)->firstOrFail();
                    $transaction = $this->finances->makeEntityTransaction(
                        $sponsorAccount,
                        $clubAccount,
                        EntityTransactionDirection::ENTITY_TO_CLUB,
                        EntityTransactionType::SPONSORSHIP,
                        $lockedContract->annual_amount,
                        CarbonImmutable::parse($date),
                        $lockedContract->id,
                    );

                    SponsorshipPayment::query()->create([
                        'sponsorship_contract_id' => $lockedContract->id,
                        'season_id' => $instance->season_id,
                        'transaction_id' => $transaction->id,
                        'amount' => $lockedContract->annual_amount,
                        'paid_on' => $date,
                    ]);
                });
            });
    }
}
